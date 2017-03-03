<?php
/**
 * SwooleDistributedServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server;

use PG\MSF\Server\Client\Client;
use PG\MSF\Server\CoreBase\CoroutineTask;
use PG\MSF\Server\CoreBase\GeneratorContext;
use PG\MSF\Server\CoreBase\InotifyProcess;
use PG\MSF\Server\CoreBase\SwooleException;
use PG\MSF\Server\DataBase\AsynPool;
use PG\MSF\Server\DataBase\AsynPoolManager;
use PG\MSF\Server\DataBase\Miner;
use PG\MSF\Server\DataBase\MysqlAsynPool;
use PG\MSF\Server\DataBase\RedisAsynPool;
use PG\MSF\Server\Test\TestModule;

abstract class SwooleDistributedServer extends SwooleWebSocketServer
{
    const SERVER_NAME = "SERVER";
    /**
     * 实例
     * @var SwooleServer
     */
    private static $instance;
    /**
     * @var RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var MysqlAsynPool
     */
    public $mysql_pool;
    /**
     * 各种client
     * @var Client
     */
    public $client;
    /**
     * 覆盖set配置
     * @var array
     */
    public $overrideSetConfig = [];
    /**
     * 404缓存
     * @var string
     */
    public $cache404;
    /**
     * 生成task_id的原子
     */
    public $task_atomic;
    /**
     * task_id和pid的映射
     */
    public $tid_pid_table;
    /**
     * 中断task的id内存锁
     */
    public $task_lock;
    /**
     * @var \Redis
     */
    protected $redis_client;
    /**
     * @var Miner
     */
    protected $mysql_client;
    /**
     * dispatch fd
     * @var array
     */
    protected $dispatchClientFds = [];
    /**
     * dispatch 端口
     * @var int
     */
    protected $dispatch_port;
    /**
     * 共享内存表
     * @var \swoole_table
     */
    protected $uid_fd_table;
    /**
     * 连接池进程
     * @var
     */
    protected $pool_process;
    /**
     * 分布式系统服务器唯一标识符
     * @var int
     */
    private $USID;
    /**
     * @var AsynPoolManager
     */
    private $asnyPoolManager;
    /**
     * 多少人启用task进行发送
     * @var
     */
    private $send_use_task_num;
    /**
     * 定时器
     * @var array
     */
    private $timer_tasks_used;
    /**
     * 初始化的锁
     * @var \swoole_lock
     */
    private $initLock;
    /**
     * 连接池
     * @var
     */
    private $asynPools;

    /**
     * SwooleDistributedServer constructor.
     */
    public function __construct()
    {
        self::$instance =& $this;
        $this->name = self::SERVER_NAME;
        parent::__construct();
    }

    /**
     * 获取实例
     * @return SwooleDistributedServer
     */
    public static function &get_instance()
    {
        return self::$instance;
    }

    public function start()
    {
        // @todo 更好的实现方式 by xudianyang
        // $this->clearState();
        return parent::start();
    }

    /**
     * 清除状态
     * @throws SwooleException
     */
    public function clearState()
    {
        print("是否清除Redis上的用户状态信息(y/n)？");
        $clear_redis = shell_read();
        if (strtolower($clear_redis) == 'y') {
            echo "[初始化] 清除Redis上用户状态。\n";
            $redis_pool = new RedisAsynPool($this->config, $this->config->get('redis.active'));
            $redis_pool->getSync()->del(SwooleMarco::redis_uid_usid_hash_name);
            $redis_pool->getSync()->close();
            unset($redis_pool);
        }
    }

    /**
     * 获取同步mysql
     * @return Miner
     * @throws SwooleException
     */
    public function getMysql()
    {
        return $this->mysql_pool->getSync();
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        parent::setConfig();
        $this->send_use_task_num = $this->config['server']['send_use_task_num'];
    }

    /**
     * 开始前创建共享内存保存USID值
     */
    public function beforeSwooleStart()
    {
        parent::beforeSwooleStart();
        //创建uid->fd共享内存表
        $this->uid_fd_table = new \swoole_table(65536);
        $this->uid_fd_table->column('fd', \swoole_table::TYPE_INT, 8);
        $this->uid_fd_table->create();
        //创建task用的taskid
        $this->task_atomic = new \swoole_atomic(0);
        //创建task用的id->pid共享内存表不至于同时超过1024个任务吧
        $this->tid_pid_table = new \swoole_table(1024);
        $this->tid_pid_table->column('pid', \swoole_table::TYPE_INT, 8);
        $this->tid_pid_table->column('des', \swoole_table::TYPE_STRING, 50);
        $this->tid_pid_table->column('st', \swoole_table::TYPE_INT, 8);
        $this->tid_pid_table->create();
        //创建task用的锁
        $this->task_lock = new \swoole_lock(SWOOLE_MUTEX);
        //创建异步连接池进程
        if ($this->config->get('asyn_process_enable', false)) {//代表启动单独进程进行管理
            $this->pool_process = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-ASYN');
                $this->asnyPoolManager = new AsynPoolManager($process, $this);
                $this->asnyPoolManager->event_add();
                $this->initAsynPools();
                foreach ($this->asynPools as $pool) {
                    $this->asnyPoolManager->registAsyn($pool);
                }
            }, false, 2);
            $this->server->addProcess($this->pool_process);
        }
        //reload监控进程
        if ($this->config->get('auto_reload_enable', false)) {//代表启动单独进程进行reload管理
            $reload_process = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-RELOAD');
                new InotifyProcess($this->server);
            }, false, 2);
            $this->server->addProcess($reload_process);
        }
        if ($this->config->get('use_dispatch')) {
            //创建dispatch端口用于连接dispatch
            $this->dispatch_port = $this->server->listen($this->config['tcp']['socket'],
                $this->config['server']['dispatch_port'], SWOOLE_SOCK_TCP);
            $this->dispatch_port->set($this->setServerSet());
            $this->dispatch_port->on('close', function ($serv, $fd) {
                print_r("Remove a dispatcher.\n");
                for ($i = 0; $i < $this->worker_num + $this->task_num; $i++) {
                    if ($i == $serv->worker_id) {
                        continue;
                    }
                    $data = $this->packSerevrMessageBody(SwooleMarco::REMOVE_DISPATCH_CLIENT, $fd);
                    $serv->sendMessage($data, $i);
                }
                $this->removeDispatch($fd);
            });

            $this->dispatch_port->on('receive', function ($serv, $fd, $from_id, $data) {
                $data = unpack($this->package_length_type . "1length/a*data", $data)['data'];
                $unserialize_data = unserialize($data);
                $type = $unserialize_data['type'];
                $message = $unserialize_data['message'];
                switch ($type) {
                    case SwooleMarco::MSG_TYPE_USID://获取服务器唯一id
                        print_r("Find a new dispatcher.\n");
                        $uns_data = unserialize($message);
                        $uns_data['fd'] = $fd;
                        $fdinfo = $this->server->connection_info($fd);
                        $uns_data['remote_ip'] = $fdinfo['remote_ip'];
                        $send_data = $this->packSerevrMessageBody($type, $uns_data);
                        for ($i = 0; $i < $this->worker_num + $this->task_num; $i++) {
                            if ($i == $serv->worker_id) {
                                continue;
                            }
                            $serv->sendMessage($send_data, $i);
                        }
                        $this->addDispatch($uns_data);
                        break;
                    case SwooleMarco::MSG_TYPE_SEND://发送消息
                        $this->sendToUid($message['uid'], $message['data'], true);
                        break;
                    case SwooleMarco::MSG_TYPE_SEND_BATCH://批量消息
                        $this->sendToUids($message['uids'], $message['data'], true);
                        break;
                    case SwooleMarco::MSG_TYPE_SEND_ALL://广播消息
                        $serv->task($data);
                        break;
                    case SwooleMarco::MSG_TYPE_KICK_UID://踢人
                        $this->kickUid($message['uid'], true);
                        break;
                }
            });
        }
        $this->initLock = new \swoole_lock(SWOOLE_RWLOCK);
    }

    /**
     * 初始化各种连接池
     */
    public function initAsynPools()
    {
        $this->asynPools = [
            'redisPool' => new RedisAsynPool($this->config, $this->config->get('redis.active')),
            'mysqlPool' => new MysqlAsynPool($this->config, $this->config->get('database.active')),
        ];
    }

    /**
     * 设置服务器配置参数
     * @return array
     */
    public function setServerSet()
    {
        $set = $this->config->get('server.set', []);
        $set = array_merge($set, $this->probuf_set);
        $set = array_merge($set, $this->overrideSetConfig);
        $this->worker_num = $set['worker_num'];
        $this->task_num = $set['task_worker_num'];
        return $set;
    }

    /**
     * 移除dispatch
     * @param $fd
     */
    public function removeDispatch($fd)
    {
        unset($this->dispatchClientFds[$fd]);
    }

    /**
     * 添加一个dispatch
     * @param $data
     */
    public function addDispatch($data)
    {
        $this->USID = $data['usid'];
        $this->dispatchClientFds[$data['fd']] = $data['fd'];
    }

    /**
     * 向uid发送消息
     * @param $uid
     * @param $data
     * @param $fromDispatch
     */
    public function sendToUid($uid, $data, $fromDispatch = false)
    {
        if (!$fromDispatch) {
            $data = $this->encode($this->pack->pack($data));
        }
        if ($this->uid_fd_table->exist($uid)) {//本机处理
            $fd = $this->uid_fd_table->get($uid)['fd'];
            $this->send($fd, $data);
        } else {
            if ($fromDispatch) {
                return;
            }
            $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_SEND, ['data' => $data, 'uid' => $uid]);
        }
    }

    /**
     * 随机选择一个dispatch发送消息
     * @param $data
     */
    private function sendToDispatchMessage($type, $data)
    {
        $send_data = $this->packSerevrMessageBody($type, $data);
        $fd = null;
        if (count($this->dispatchClientFds) > 0) {
            $fd = $this->dispatchClientFds[array_rand($this->dispatchClientFds)];
        }
        if ($fd != null) {
            $this->server->send($fd, $this->encode($send_data));
        } else {
            //如果没有dispatch那么MSG_TYPE_SEND_BATCH这个消息不需要发出，因为本机已经处理过可以发送的uid了
            if ($type == SwooleMarco::MSG_TYPE_SEND_BATCH) {
                return;
            }
            if ($this->isTaskWorker()) {
                $this->onSwooleTask($this->server, 0, 0, $send_data);
            } else {
                $this->server->task($send_data);
            }
        }
    }

    /**
     * task异步任务
     * @param $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return mixed|null
     * @throws SwooleException
     */
    public function onSwooleTask($serv, $task_id, $from_id, $data)
    {
        if (is_string($data)) {
            $unserialize_data = unserialize($data);
        } else {
            $unserialize_data = $data;
        }
        $type = $unserialize_data['type']??'';
        $message = $unserialize_data['message']??'';
        switch ($type) {
            case SwooleMarco::MSG_TYPE_SEND_BATCH://发送消息
                foreach ($message['fd'] as $fd) {
                    $this->send($fd, $message['data']);
                }
                return null;
            case SwooleMarco::MSG_TYPE_SEND_ALL://发送广播
                foreach ($serv->connections as $fd) {
                    if (in_array($fd, $this->dispatchClientFds)) {
                        continue;
                    }
                    $this->send($fd, $message['data']);
                }
                return null;
            case SwooleMarco::MSG_TYPE_SEND_GROUP://群组
                $uids = $this->getRedis()->sMembers(SwooleMarco::redis_group_hash_name_prefix . $message['groupId']);
                foreach ($uids as $uid) {
                    if ($this->uid_fd_table->exist($uid)) {
                        $fd = $this->uid_fd_table->get($uid)['fd'];
                        $this->send($fd, $message['data']);
                    }
                }
                return null;
            case SwooleMarco::SERVER_TYPE_TASK://task任务
                $task_name = $message['task_name'];
                $task = $this->loader->task($task_name, $this);
                $task_fuc_name = $message['task_fuc_name'];
                $task_data = $message['task_fuc_data'];
                $task_id = $message['task_id'];
                $task_context = $message['task_context'];
                if (method_exists($task, $task_fuc_name)) {
                    //给task做初始化操作
                    $task->initialization($task_id, $this->server->worker_pid, $task_name, $task_fuc_name,
                        $task_context);
                    $result = call_user_func_array(array($task, $task_fuc_name), $task_data);
                    if ($result instanceof \Generator) {
                        $corotineTask = new CoroutineTask($result, new GeneratorContext());
                        while (1) {
                            if ($corotineTask->isFinished()) {
                                $result = $result->getReturn();
                                $corotineTask->destroy();
                                break;
                            }
                            $corotineTask->run();
                        }
                    }
                } else {
                    throw new SwooleException("method $task_fuc_name not exist in $task_name");
                }
                $task->destroy();
                return $result;
            default:
                return parent::onSwooleTask($serv, $task_id, $from_id, $data);
        }
    }

    /**
     * 获取同步redis
     * @return \Redis
     * @throws SwooleException
     */
    public function getRedis()
    {
        return $this->redis_pool->getSync();
    }

    /**
     * 批量发送消息
     * @param $uids
     * @param $data
     * @param $fromDispatch
     */
    public function sendToUids($uids, $data, $fromDispatch = false)
    {
        if (!$fromDispatch) {
            $data = $this->encode($this->pack->pack($data));
        }
        $current_fds = [];
        foreach ($uids as $key => $uid) {
            if ($this->uid_fd_table->exist($uid)) {
                $current_fds[] = $this->uid_fd_table->get($uid)['fd'];
                unset($uids[$key]);
            }
        }
        if (count($current_fds) > $this->send_use_task_num) {//过多人就通过task
            $task_data = $this->packSerevrMessageBody(SwooleMarco::MSG_TYPE_SEND_BATCH,
                ['data' => $data, 'fd' => $current_fds]);
            if ($this->isTaskWorker()) {
                $this->onSwooleTask($this->server, 0, 0, $task_data);
            } else {
                $this->server->task($task_data);
            }
        } else {
            foreach ($current_fds as $fd) {
                $this->send($fd, $data);
            }
        }
        if ($fromDispatch) {
            return;
        }
        //本机处理不了的发给dispatch
        if (count($uids) > 0) {
            $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_SEND_BATCH,
                ['data' => $data, 'uids' => array_values($uids)]);
        }
    }

    /**
     * 踢用户下线
     * @param $uid
     * @param bool $fromDispatch
     */
    public function kickUid($uid, $fromDispatch = false)
    {
        if ($this->uid_fd_table->exist($uid)) {//本机处理
            $fd = $this->uid_fd_table->get($uid)['fd'];
            $this->close($fd);
        } else {
            if ($fromDispatch) {
                return;
            }
            $usid = $this->getRedis()->hGet(SwooleMarco::redis_uid_usid_hash_name, $uid);
            $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_KICK_UID, ['usid' => $usid, 'uid' => $uid]);
        }
    }

    /**
     * PipeMessage
     * @param $serv
     * @param $from_worker_id
     * @param $message
     */
    public function onSwoolePipeMessage($serv, $from_worker_id, $message)
    {
        parent::onSwoolePipeMessage($serv, $from_worker_id, $message);
        $data = unserialize($message);
        switch ($data['type']) {
            case SwooleMarco::MSG_TYPE_USID:
                $this->addDispatch($data['message']);
                break;
            case SwooleMarco::REMOVE_DISPATCH_CLIENT:
                $this->removeDispatch($data['message']);
                break;
            case SwooleMarco::MSG_TYPR_ASYN:
                $this->asnyPoolManager->distribute($data['message']);
                break;
        }
    }

    /**
     * 添加AsynPool
     * @param $name
     * @param AsynPool $pool
     * @throws SwooleException
     */
    public function addAsynPool($name, AsynPool $pool)
    {
        if (key_exists($name, $this->asynPools)) {
            throw  new SwooleException('pool key is exists!');
        }
        $this->asynPools[$name] = $pool;
    }

    /**
     * 获取连接池
     * @param $name
     * @return AsynPool
     */
    public function getAsynPool($name)
    {
        return $this->asynPools[$name];
    }

    /**
     * 重写onSwooleWorkerStart方法，添加异步redis
     * @param $serv
     * @param $workerId
     * @throws SwooleException
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        $this->initAsynPools();
        $this->redis_pool = $this->asynPools['redisPool'];
        $this->mysql_pool = $this->asynPools['mysqlPool'];
        if (!$serv->taskworker) {
            //注册
            $this->asnyPoolManager = new AsynPoolManager($this->pool_process, $this);
            if (!$this->config['asyn_process_enable']) {
                $this->asnyPoolManager->no_event_add();
            }
            foreach ($this->asynPools as $pool) {

                $pool->worker_init($workerId);
                $this->asnyPoolManager->registAsyn($pool);
            }
            //初始化异步Client
            $this->client = new Client();
        } else {
            //注册中断信号
            pcntl_signal(SIGUSR1, function () {

            });
        }
        //进程锁
        if (!$this->isTaskWorker() && $this->initLock->trylock()) {
            //进程启动后进行开服的初始化
            $generator = $this->onOpenServiceInitialization();
            if ($generator instanceof \Generator) {
                $generatorContext = new GeneratorContext();
                $generatorContext->setController(null, 'SwooleDistributedServer', 'onSwooleWorkerStart');
                $this->coroutine->start($generator, $generatorContext);
            }
            if (SwooleServer::$testUnity) {
                new TestModule(SwooleServer::$testUnityDir, $this->coroutine);
            }
            $this->initLock->lock_read();
        }
        //最后一个worker处理启动定时器
        if ($workerId == $this->worker_num - 1) {
            //重新读入timerTask配置
            $timerTaskConfig = $this->config->load(ROOT_PATH . '/config/timerTask.php');
            $timer_tasks = $timerTaskConfig->get('timerTask');
            $this->timer_tasks_used = array();

            foreach ($timer_tasks as $timer_task) {
                $task_name = $timer_task['task_name']??'';
                $model_name = $timer_task['model_name']??'';
                if (empty($task_name) && empty($model_name)) {
                    throw new SwooleException('定时任务配置错误，缺少task_name或者model_name.');
                }
                $method_name = $timer_task['method_name'];
                if (!key_exists('start_time', $timer_task)) {
                    $start_time = -1;
                } else {
                    $start_time = strtotime(date($timer_task['start_time']));
                }
                if (!key_exists('end_time', $timer_task)) {
                    $end_time = -1;
                } else {
                    $end_time = strtotime(date($timer_task['end_time']));
                }
                if (!key_exists('delay', $timer_task)) {
                    $delay = false;
                } else {
                    $delay = $timer_task['delay'];
                }
                $interval_time = $timer_task['interval_time'] < 1 ? 1 : $timer_task['interval_time'];
                $max_exec = $timer_task['max_exec']??-1;
                $this->timer_tasks_used[] = [
                    'task_name' => $task_name,
                    'model_name' => $model_name,
                    'method_name' => $method_name,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'interval_time' => $interval_time,
                    'max_exec' => $max_exec,
                    'now_exec' => 0,
                    'delay' => $delay
                ];
            }
            if (count($this->timer_tasks_used) > 0) {
                $this->timerTask();
                $serv->tick(1000, [$this, 'timerTask']);
            }
        }
    }

    /**
     * 开服初始化(支持协程)
     * @return mixed
     */
    public function onOpenServiceInitialization()
    {
        if ($this->config->get('autoClearGroup', false)) {
            $this->delAllGroups();
            print_r("[初始化] 清除redis上所有群信息。\n");
        }
    }

    /**
     * 删除所有的群
     */
    public function delAllGroups()
    {
        if ($this->isTaskWorker()) {
            $groups = $this->getAllGroups(null);
            foreach ($groups as $key => $group_id) {
                $groups[$key] = SwooleMarco::redis_group_hash_name_prefix . $group_id;
            }
            $groups[] = SwooleMarco::redis_groups_hash_name;
            //删除所有的群和群管理
            $this->getRedis()->del($groups);
        } else {
            $this->getAllGroups(function ($groups) {
                foreach ($groups as $key => $group_id) {
                    $groups[$key] = SwooleMarco::redis_group_hash_name_prefix . $group_id;
                }
                $groups[] = SwooleMarco::redis_groups_hash_name;
                //删除所有的群和群管理
                $this->redis_pool->del($groups, null);
            });
        }
    }

    /**
     * 获取所有的群id(异步时候需要提供callback,task可以直接返回结果)
     * @param $callback
     * @return array
     */
    public function getAllGroups($callback)
    {
        if ($this->isTaskWorker()) {
            return $this->getRedis()->sMembers(SwooleMarco::redis_groups_hash_name);
        } else {
            $this->redis_pool->sMembers(SwooleMarco::redis_groups_hash_name, $callback);
        }
    }

    /**
     * 定时任务
     */
    public function timerTask()
    {
        $time = time();
        foreach ($this->timer_tasks_used as &$timer_task) {
            if ($timer_task['start_time'] < $time && $timer_task['start_time'] != -1) {
                $count = round(($time - $timer_task['start_time']) / $timer_task['interval_time']);
                $timer_task['start_time'] += $count * $timer_task['interval_time'];
            }
            if (($time == $timer_task['start_time'] || $timer_task['start_time'] == -1) &&
                ($time < $timer_task['end_time'] || $timer_task['end_time'] = -1) &&
                ($timer_task['now_exec'] < $timer_task['max_exec'] || $timer_task['max_exec'] == -1)
            ) {
                if ($timer_task['delay']) {
                    if ($timer_task['start_time'] == -1) {
                        $timer_task['start_time'] = $time;
                    }
                    $timer_task['start_time'] += $timer_task['interval_time'];
                    $timer_task['delay'] = false;
                    continue;
                }
                $timer_task['now_exec']++;
                if ($timer_task['start_time'] == -1) {
                    $timer_task['start_time'] = $time;
                }
                $timer_task['start_time'] += $timer_task['interval_time'];
                if (!empty($timer_task['task_name'])) {
                    $task = $this->loader->task($timer_task['task_name'], $this);
                    call_user_func([$task, $timer_task['method_name']]);
                    $task->startTask(null);
                } else {
                    $model = $this->loader->model($timer_task['model_name'], $this);
                    $generator = call_user_func([$model, $timer_task['method_name']]);
                    if ($generator instanceof \Generator) {
                        $generatorContext = new GeneratorContext();
                        $generatorContext->setController(null, $timer_task['model_name'], $timer_task['method_name']);
                        $this->coroutine->start($generator, $generatorContext);
                    }
                }
            }
        }
    }

    /**
     * 连接断开
     * @param $serv
     * @param $fd
     */
    public function onSwooleClose($serv, $fd)
    {
        $info = $serv->connection_info($fd, 0, true);
        $uid = $info['uid']??0;
        if (!empty($uid)) {
            $generator = $this->onUidCloseClear($uid);
            if ($generator instanceof \Generator) {
                $generatorContext = new GeneratorContext();
                $generatorContext->setController(null, 'SwooleDistributedServer', 'onSwooleClose');
                $this->coroutine->start($generator, $generatorContext);
            }
            $this->unBindUid($uid);
        }
        parent::onSwooleClose($serv, $fd);
    }

    /**
     * 当一个绑定uid的连接close后的清理
     * 支持协程
     * @param $uid
     */
    abstract public function onUidCloseClear($uid);

    /**
     * 解绑uid，链接断开自动解绑
     * @param $uid
     */
    public function unBindUid($uid)
    {
        //更新共享内存
        $ok = $this->uid_fd_table->del($uid);
        //更新映射表
        if ($ok) {//说明是本机绑定的uid
            $this->getRedis()->hDel(SwooleMarco::redis_uid_usid_hash_name, $uid);
        }
    }

    /**
     * 将fd绑定到uid,uid不能为0
     * @param $fd
     * @param $uid
     * @param bool $isKick 是否踢掉uid上一个的链接
     */
    public function bindUid($fd, $uid, $isKick = true)
    {
        if ($isKick) {
            $this->kickUid($uid, false);
        }
        $this->getRedis()->hSet(SwooleMarco::redis_uid_usid_hash_name, $uid, $this->USID);
        //将这个fd与当前worker进行绑定
        $this->server->bind($fd, $uid);
        //加入共享内存
        $this->uid_fd_table->set($uid, ['fd' => $fd]);
    }

    /**
     * uid是否在线(协程)
     * @param $uid
     * @return int
     * @throws SwooleException
     */
    public function coroutineUidIsOnline($uid)
    {
        return yield $this->redis_pool->getCoroutine()->hExists(SwooleMarco::redis_uid_usid_hash_name, $uid);
    }


    /**
     * 获取在线人数(协程)
     * @return int
     * @throws SwooleException
     */
    public function coroutineCountOnline()
    {
        return yield $this->redis_pool->getCoroutine()->hLen(SwooleMarco::redis_uid_usid_hash_name);
    }

    /**
     * 获取所有的群id（协程）
     * @return array
     * @throws SwooleException
     */
    public function coroutineGetAllGroups()
    {
        return yield $this->redis_pool->getCoroutine()->sMembers(SwooleMarco::redis_groups_hash_name);
    }

    /**
     * 添加到群(可以支持批量,实际是否支持根据sdk版本测试)
     * @param $uid int | array
     * @param $group_id int
     */
    public function addToGroup($uid, $group_id)
    {
        if ($this->isTaskWorker()) {
            //放入群管理中
            $this->getRedis()->sAdd(SwooleMarco::redis_groups_hash_name, $group_id);
            //放入对应的群中
            $this->getRedis()->sAdd(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid);
        } else {
            //放入群管理中
            $this->redis_pool->sAdd(SwooleMarco::redis_groups_hash_name, $group_id, null);
            //放入对应的群中
            $this->redis_pool->sAdd(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid, null);
        }
    }


    /**
     * 从群里移除(可以支持批量,实际是否支持根据sdk版本测试)
     * @param $uid int | array
     * @param $group_id
     */
    public function removeFromGroup($uid, $group_id)
    {
        if ($this->isTaskWorker()) {
            $this->getRedis()->sRem(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid);
        } else {
            $this->redis_pool->sRem(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid, null);
        }
    }

    /**
     * 删除群
     * @param $group_id
     */
    public function delGroup($group_id)
    {
        if ($this->isTaskWorker()) {
            //从群管理中删除
            $this->getRedis()->sRem(SwooleMarco::redis_groups_hash_name, $group_id);
            //删除这个群
            $this->getRedis()->del(SwooleMarco::redis_group_hash_name_prefix . $group_id);
        } else {
            //从群管理中删除
            $this->redis_pool->sRem(SwooleMarco::redis_groups_hash_name, $group_id, null);
            //删除这个群
            $this->redis_pool->del(SwooleMarco::redis_group_hash_name_prefix . $group_id, null);
        }
    }

    /**
     * 获取群的人数（协程）
     * @param $group_id
     * @return int
     * @throws SwooleException
     */
    public function coroutineGetGroupCount($group_id)
    {
        return yield $this->redis_pool->getCoroutine()->sCard(SwooleMarco::redis_group_hash_name_prefix . $group_id);
    }

    /**
     * 获取群成员uids (协程)
     * @param $group_id
     * @return array
     * @throws SwooleException
     */
    public function coroutineGetGroupUids($group_id)
    {
        return yield $this->redis_pool->getCoroutine()->sMembers(SwooleMarco::redis_group_hash_name_prefix . $group_id);
    }

    /**
     * 广播
     * @param $data
     */
    public function sendToAll($data)
    {
        $data = $this->encode($this->pack->pack($data));
        $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_SEND_ALL, ['data' => $data]);
    }

    /**
     * 发送给群
     * @param $groupId
     * @param $data
     */
    public function sendToGroup($groupId, $data)
    {
        $data = $this->encode($this->pack->pack($data));
        $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_SEND_GROUP, ['data' => $data, 'groupId' => $groupId]);
    }
}