<?php
/**
 * SwooleDistributedServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server;

use PG\MSF\Client\{
    Http\Client as HttpClient, Tcp\Client as TcpClient
};
use PG\MSF\Server\Test\TestModule;
use PG\MSF\Server\Coroutine\CoroutineTask;
use PG\MSF\Server\CoreBase\{
    GeneratorContext, InotifyProcess, SwooleException
};
use PG\MSF\Server\DataBase\{
    AsynPool, AsynPoolManager, Miner, MysqlAsynPool, RedisAsynPool
};

abstract class MSFServer extends WebSocketServer
{
    const SERVER_NAME = "SERVER";
    /**
     * 实例
     * @var Server
     */
    private static $instance;
    /**
     * @var RedisAsynPool
     */
    public $redisPool;
    /**
     * @var MysqlAsynPool
     */
    public $mysqlPool;
    /**
     * http client
     * @var HttpClient
     */
    public $client;

    /**
     * tcp client
     * @var TcpClient
     */
    public $tcpClient;
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
    public $taskAtomic;
    /**
     * task_id和pid的映射
     */
    public $tidPidTable;
    /**
     * 中断task的id内存锁
     */
    public $taskLock;
    /**
     * @var \Redis
     */
    protected $redisClient;
    /**
     * @var Miner
     */
    protected $mysqlClient;
    /**
     * dispatch fd
     * @var array
     */
    protected $dispatchClientFds = [];
    /**
     * dispatch 端口
     * @var int
     */
    protected $dispatchPort;
    /**
     * 共享内存表
     * @var \swoole_table
     */
    protected $uidFdTable;
    /**
     * 连接池进程
     * @var
     */
    protected $poolProcess;
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
    private $sendUseTaskNum;
    /**
     * 定时器
     * @var array
     */
    private $timerTasksUsed;
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
     * @return MSFServer
     */
    public static function &getInstance()
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
        $clearRedis = shellRead();
        if (strtolower($clearRedis) == 'y') {
            echo "[初始化] 清除Redis上用户状态。\n";
            $redisPool = new RedisAsynPool($this->config, $this->config->get('redis.active'));
            $redisPool->getSync()->del(Marco::redis_uid_usid_hash_name);
            $redisPool->getSync()->close();
            unset($redisPool);
        }
    }

    /**
     * 获取同步mysql
     * @return Miner
     * @throws SwooleException
     */
    public function getMysql()
    {
        return $this->mysqlPool->getSync();
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        parent::setConfig();
        $this->sendUseTaskNum = $this->config['server']['send_use_task_num'];
    }

    /**
     * 开始前创建共享内存保存USID值
     */
    public function beforeSwooleStart()
    {
        parent::beforeSwooleStart();

        //创建uid->fd共享内存表
        $this->uidFdTable = new \swoole_table(65536);
        $this->uidFdTable->column('fd', \swoole_table::TYPE_INT, 8);
        $this->uidFdTable->create();

        //创建task用的Atomic
        $this->taskAtomic = new \swoole_atomic(0);

        //创建task用的id->pid共享内存表不至于同时超过1024个任务吧
        $this->tidPidTable = new \swoole_table(1024);
        $this->tidPidTable->column('pid', \swoole_table::TYPE_INT, 8);
        $this->tidPidTable->column('des', \swoole_table::TYPE_STRING, 50);
        $this->tidPidTable->column('start_time', \swoole_table::TYPE_INT, 8);
        $this->tidPidTable->create();

        //创建task用的锁
        $this->taskLock = new \swoole_lock(SWOOLE_MUTEX);

        //创建异步连接池进程
        if ($this->config->get('asyn_process_enable', false)) {//代表启动单独进程进行管理
            $this->poolProcess = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-ASYN');
                $this->asnyPoolManager = new AsynPoolManager($process, $this);
                $this->asnyPoolManager->event_add();
                $this->initAsynPools();
                foreach ($this->asynPools as $pool) {
                    $this->asnyPoolManager->registAsyn($pool);
                }
            }, false, 2);
            $this->server->addProcess($this->poolProcess);
        }

        //reload监控进程
        if ($this->config->get('auto_reload_enable', false)) {//代表启动单独进程进行reload管理
            $reloadProcess = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-RELOAD');
                new InotifyProcess($this->server);
            }, false, 2);
            $this->server->addProcess($reloadProcess);
        }

        if ($this->config->get('use_dispatch')) {
            //创建dispatch端口用于连接dispatch
            $this->dispatchPort = $this->server->listen($this->config['tcp']['socket'],
                $this->config['server']['dispatch_port'], SWOOLE_SOCK_TCP);
            $this->dispatchPort->set($this->setServerSet());
            $this->dispatchPort->on('close', function ($serv, $fd) {
                print_r("Remove a dispatcher.\n");
                for ($i = 0; $i < $this->workerNum + $this->taskNum; $i++) {
                    if ($i == $serv->workerId) {
                        continue;
                    }
                    $data = $this->packSerevrMessageBody(Marco::REMOVE_DISPATCH_CLIENT, $fd);
                    $serv->sendMessage($data, $i);
                }
                $this->removeDispatch($fd);
            });

            $this->dispatchPort->on('receive', function ($serv, $fd, $fromId, $data) {
                $data = unpack($this->packageLengthType . "1length/a*data", $data)['data'];
                $unserializeData = unserialize($data);
                $type = $unserializeData['type'];
                $message = $unserializeData['message'];
                switch ($type) {
                    case Marco::MSG_TYPE_USID://获取服务器唯一id
                        print_r("Find a new dispatcher.\n");
                        $unsData = unserialize($message);
                        $unsData['fd'] = $fd;
                        $fdinfo = $this->server->connection_info($fd);
                        $unsData['remote_ip'] = $fdinfo['remote_ip'];
                        $sendData = $this->packSerevrMessageBody($type, $unsData);
                        for ($i = 0; $i < $this->workerNum + $this->taskNum; $i++) {
                            if ($i == $serv->workerId) {
                                continue;
                            }
                            $serv->sendMessage($sendData, $i);
                        }
                        $this->addDispatch($unsData);
                        break;
                    case Marco::MSG_TYPE_SEND://发送消息
                        $this->sendToUid($message['uid'], $message['data'], true);
                        break;
                    case Marco::MSG_TYPE_SEND_BATCH://批量消息
                        $this->sendToUids($message['uids'], $message['data'], true);
                        break;
                    case Marco::MSG_TYPE_SEND_ALL://广播消息
                        $serv->task($data);
                        break;
                    case Marco::MSG_TYPE_KICK_UID://踢人
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
        $set = array_merge($set, $this->probufSet);
        $set = array_merge($set, $this->overrideSetConfig);
        $this->workerNum = $set['worker_num'];
        $this->taskNum = $set['task_worker_num'];
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
        if ($this->uidFdTable->exist($uid)) {//本机处理
            $fd = $this->uidFdTable->get($uid)['fd'];
            $this->send($fd, $data);
        } else {
            if ($fromDispatch) {
                return;
            }
            $this->sendToDispatchMessage(Marco::MSG_TYPE_SEND, ['data' => $data, 'uid' => $uid]);
        }
    }

    /**
     * 随机选择一个dispatch发送消息
     * @param $data
     */
    private function sendToDispatchMessage($type, $data)
    {
        $sendData = $this->packSerevrMessageBody($type, $data);
        $fd = null;
        if (count($this->dispatchClientFds) > 0) {
            $fd = $this->dispatchClientFds[array_rand($this->dispatchClientFds)];
        }
        if ($fd != null) {
            $this->server->send($fd, $this->encode($sendData));
        } else {
            //如果没有dispatch那么MSG_TYPE_SEND_BATCH这个消息不需要发出，因为本机已经处理过可以发送的uid了
            if ($type == Marco::MSG_TYPE_SEND_BATCH) {
                return;
            }
            if ($this->isTaskWorker()) {
                $this->onSwooleTask($this->server, 0, 0, $sendData);
            } else {
                $this->server->task($sendData);
            }
        }
    }

    /**
     * task异步任务
     * @param $serv
     * @param $taskId
     * @param $fromId
     * @param $data
     * @return mixed|null
     * @throws SwooleException
     */
    public function onSwooleTask($serv, $taskId, $fromId, $data)
    {
        if (is_string($data)) {
            $unserializeData = unserialize($data);
        } else {
            $unserializeData = $data;
        }
        $type = $unserializeData['type']??'';
        $message = $unserializeData['message']??'';
        switch ($type) {
            case Marco::MSG_TYPE_SEND_BATCH://发送消息
                foreach ($message['fd'] as $fd) {
                    $this->send($fd, $message['data']);
                }
                return null;
            case Marco::MSG_TYPE_SEND_ALL://发送广播
                foreach ($serv->connections as $fd) {
                    if (in_array($fd, $this->dispatchClientFds)) {
                        continue;
                    }
                    $this->send($fd, $message['data']);
                }
                return null;
            case Marco::MSG_TYPE_SEND_GROUP://群组
                $uids = $this->getRedis()->sMembers(Marco::redis_group_hash_name_prefix . $message['groupId']);
                foreach ($uids as $uid) {
                    if ($this->uidFdTable->exist($uid)) {
                        $fd = $this->uidFdTable->get($uid)['fd'];
                        $this->send($fd, $message['data']);
                    }
                }
                return null;
            case Marco::SERVER_TYPE_TASK://task任务
                $taskName = $message['task_name'];
                $task = $this->loader->task($taskName, $this);
                $taskFucName = $message['task_fuc_name'];
                $taskData = $message['task_fuc_data'];
                $taskId = $message['task_id'];
                $taskContext = $message['task_context'];
                if (method_exists($task, $taskFucName)) {
                    //给task做初始化操作
                    $task->initialization($taskId, $this->server->worker_pid, $taskName, $taskFucName,
                        $taskContext);
                    $result = call_user_func_array(array($task, $taskFucName), $taskData);
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
                    throw new SwooleException("method $taskFucName not exist in $taskName");
                }
                $task->destroy();
                return $result;
            default:
                return parent::onSwooleTask($serv, $taskId, $fromId, $data);
        }
    }

    /**
     * 获取同步redis
     * @return \Redis
     * @throws SwooleException
     */
    public function getRedis()
    {
        return $this->redisPool->getSync();
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
        $currentFds = [];
        foreach ($uids as $key => $uid) {
            if ($this->uidFdTable->exist($uid)) {
                $currentFds[] = $this->uidFdTable->get($uid)['fd'];
                unset($uids[$key]);
            }
        }
        if (count($currentFds) > $this->sendUseTaskNum) {//过多人就通过task
            $taskData = $this->packSerevrMessageBody(Marco::MSG_TYPE_SEND_BATCH,
                ['data' => $data, 'fd' => $currentFds]);
            if ($this->isTaskWorker()) {
                $this->onSwooleTask($this->server, 0, 0, $taskData);
            } else {
                $this->server->task($taskData);
            }
        } else {
            foreach ($currentFds as $fd) {
                $this->send($fd, $data);
            }
        }
        if ($fromDispatch) {
            return;
        }
        //本机处理不了的发给dispatch
        if (count($uids) > 0) {
            $this->sendToDispatchMessage(Marco::MSG_TYPE_SEND_BATCH,
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
        if ($this->uidFdTable->exist($uid)) {//本机处理
            $fd = $this->uidFdTable->get($uid)['fd'];
            $this->close($fd);
        } else {
            if ($fromDispatch) {
                return;
            }
            $usid = $this->getRedis()->hGet(Marco::redis_uid_usid_hash_name, $uid);
            $this->sendToDispatchMessage(Marco::MSG_TYPE_KICK_UID, ['usid' => $usid, 'uid' => $uid]);
        }
    }

    /**
     * PipeMessage
     * @param $serv
     * @param $fromWorkerId
     * @param $message
     */
    public function onSwoolePipeMessage($serv, $fromWorkerId, $message)
    {
        parent::onSwoolePipeMessage($serv, $fromWorkerId, $message);
        $data = unserialize($message);
        switch ($data['type']) {
            case Marco::MSG_TYPE_USID:
                $this->addDispatch($data['message']);
                break;
            case Marco::REMOVE_DISPATCH_CLIENT:
                $this->removeDispatch($data['message']);
                break;
            case Marco::MSG_TYPR_ASYN:
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
        $this->redisPool = $this->asynPools['redisPool'];
        $this->mysqlPool = $this->asynPools['mysqlPool'];
        if (!$serv->taskworker) {
            //注册
            $this->asnyPoolManager = new AsynPoolManager($this->poolProcess, $this);
            if (!$this->config['asyn_process_enable']) {
                $this->asnyPoolManager->noEventAdd();
            }
            foreach ($this->asynPools as $pool) {

                $pool->workerInit($workerId);
                $this->asnyPoolManager->registAsyn($pool);
            }
            //初始化异步Client
            $this->client = new HttpClient();
            $this->tcpClient = new TcpClient();
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
            if (Server::$testUnity) {
                new TestModule(Server::$testUnityDir, $this->coroutine);
            }
            $this->initLock->lock_read();
        }
        //最后一个worker处理启动定时器
        if ($workerId == $this->workerNum - 1) {
            //重新读入timerTask配置
            $timerTaskConfig = $this->config->load(ROOT_PATH . '/config/timerTask.php');
            $timerTasks = $timerTaskConfig->get('timerTask');
            $this->timerTasksUsed = array();

            foreach ($timerTasks as $timerTask) {
                $taskName = $timerTask['task_name']??'';
                $modelName = $timerTask['model_name']??'';
                if (empty($taskName) && empty($modelName)) {
                    throw new SwooleException('定时任务配置错误，缺少task_name或者model_name.');
                }
                $methodName = $timerTask['method_name'];
                if (!key_exists('start_time', $timerTask)) {
                    $startTime = -1;
                } else {
                    $startTime = strtotime(date($timerTask['start_time']));
                }
                if (!key_exists('end_time', $timerTask)) {
                    $endTime = -1;
                } else {
                    $endTime = strtotime(date($timerTask['end_time']));
                }
                if (!key_exists('delay', $timerTask)) {
                    $delay = false;
                } else {
                    $delay = $timerTask['delay'];
                }
                $intervalTime = $timerTask['interval_time'] < 1 ? 1 : $timerTask['interval_time'];
                $maxExec = $timerTask['max_exec']??-1;
                $this->timerTasksUsed[] = [
                    'task_name' => $taskName,
                    'model_name' => $modelName,
                    'method_name' => $methodName,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'interval_time' => $intervalTime,
                    'max_exec' => $maxExec,
                    'now_exec' => 0,
                    'delay' => $delay
                ];
            }
            if (count($this->timerTasksUsed) > 0) {
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
            foreach ($groups as $key => $groupId) {
                $groups[$key] = Marco::redis_group_hash_name_prefix . $groupId;
            }
            $groups[] = Marco::redis_groups_hash_name;
            //删除所有的群和群管理
            $this->getRedis()->del($groups);
        } else {
            $this->getAllGroups(function ($groups) {
                foreach ($groups as $key => $groupId) {
                    $groups[$key] = Marco::redis_group_hash_name_prefix . $groupId;
                }
                $groups[] = Marco::redis_groups_hash_name;
                //删除所有的群和群管理
                $this->redisPool->del($groups, null);
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
            return $this->getRedis()->sMembers(Marco::redis_groups_hash_name);
        } else {
            $this->redisPool->sMembers(Marco::redis_groups_hash_name, $callback);
        }
    }

    /**
     * 定时任务
     */
    public function timerTask()
    {
        $time = time();
        foreach ($this->timerTasksUsed as &$timerTask) {
            if ($timerTask['start_time'] < $time && $timerTask['start_time'] != -1) {
                $count = round(($time - $timerTask['start_time']) / $timerTask['interval_time']);
                $timerTask['start_time'] += $count * $timerTask['interval_time'];
            }
            if (($time == $timerTask['start_time'] || $timerTask['start_time'] == -1) &&
                ($time < $timerTask['end_time'] || $timerTask['end_time'] = -1) &&
                ($timerTask['now_exec'] < $timerTask['max_exec'] || $timerTask['max_exec'] == -1)
            ) {
                if ($timerTask['delay']) {
                    if ($timerTask['start_time'] == -1) {
                        $timerTask['start_time'] = $time;
                    }
                    $timerTask['start_time'] += $timerTask['interval_time'];
                    $timerTask['delay'] = false;
                    continue;
                }
                $timerTask['now_exec']++;
                if ($timerTask['start_time'] == -1) {
                    $timerTask['start_time'] = $time;
                }
                $timerTask['start_time'] += $timerTask['interval_time'];
                if (!empty($timerTask['task_name'])) {
                    $task = $this->loader->task($timerTask['task_name'], $this);
                    call_user_func([$task, $timerTask['method_name']]);
                    $task->startTask(null);
                } else {
                    $model = $this->loader->model($timerTask['model_name'], $this);
                    $generator = call_user_func([$model, $timerTask['method_name']]);
                    if ($generator instanceof \Generator) {
                        $generatorContext = new GeneratorContext();
                        $generatorContext->setController(null, $timerTask['model_name'], $timerTask['method_name']);
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
        $ok = $this->uidFdTable->del($uid);
        //更新映射表
        if ($ok) {//说明是本机绑定的uid
            $this->getRedis()->hDel(Marco::redis_uid_usid_hash_name, $uid);
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
        $this->getRedis()->hSet(Marco::redis_uid_usid_hash_name, $uid, $this->USID);
        //将这个fd与当前worker进行绑定
        $this->server->bind($fd, $uid);
        //加入共享内存
        $this->uidFdTable->set($uid, ['fd' => $fd]);
    }

    /**
     * uid是否在线(协程)
     * @param $uid
     * @return int
     * @throws SwooleException
     */
    public function coroutineUidIsOnline($uid)
    {
        return yield $this->redisPool->getCoroutine()->hExists(Marco::redis_uid_usid_hash_name, $uid);
    }


    /**
     * 获取在线人数(协程)
     * @return int
     * @throws SwooleException
     */
    public function coroutineCountOnline()
    {
        return yield $this->redisPool->getCoroutine()->hLen(Marco::redis_uid_usid_hash_name);
    }

    /**
     * 获取所有的群id（协程）
     * @return array
     * @throws SwooleException
     */
    public function coroutineGetAllGroups()
    {
        return yield $this->redisPool->getCoroutine()->sMembers(Marco::redis_groups_hash_name);
    }

    /**
     * 添加到群(可以支持批量,实际是否支持根据sdk版本测试)
     * @param $uid int | array
     * @param $groupId int
     */
    public function addToGroup($uid, $groupId)
    {
        if ($this->isTaskWorker()) {
            //放入群管理中
            $this->getRedis()->sAdd(Marco::redis_groups_hash_name, $groupId);
            //放入对应的群中
            $this->getRedis()->sAdd(Marco::redis_group_hash_name_prefix . $groupId, $uid);
        } else {
            //放入群管理中
            $this->redisPool->sAdd(Marco::redis_groups_hash_name, $groupId, null);
            //放入对应的群中
            $this->redisPool->sAdd(Marco::redis_group_hash_name_prefix . $groupId, $uid, null);
        }
    }


    /**
     * 从群里移除(可以支持批量,实际是否支持根据sdk版本测试)
     * @param $uid int | array
     * @param $groupId
     */
    public function removeFromGroup($uid, $groupId)
    {
        if ($this->isTaskWorker()) {
            $this->getRedis()->sRem(Marco::redis_group_hash_name_prefix . $groupId, $uid);
        } else {
            $this->redisPool->sRem(Marco::redis_group_hash_name_prefix . $groupId, $uid, null);
        }
    }

    /**
     * 删除群
     * @param $groupId
     */
    public function delGroup($groupId)
    {
        if ($this->isTaskWorker()) {
            //从群管理中删除
            $this->getRedis()->sRem(Marco::redis_groups_hash_name, $groupId);
            //删除这个群
            $this->getRedis()->del(Marco::redis_group_hash_name_prefix . $groupId);
        } else {
            //从群管理中删除
            $this->redisPool->sRem(Marco::redis_groups_hash_name, $groupId, null);
            //删除这个群
            $this->redisPool->del(Marco::redis_group_hash_name_prefix . $groupId, null);
        }
    }

    /**
     * 获取群的人数（协程）
     * @param $groupId
     * @return int
     * @throws SwooleException
     */
    public function coroutineGetGroupCount($groupId)
    {
        return yield $this->redisPool->getCoroutine()->sCard(Marco::redis_group_hash_name_prefix . $groupId);
    }

    /**
     * 获取群成员uids (协程)
     * @param $groupId
     * @return array
     * @throws SwooleException
     */
    public function coroutineGetGroupUids($groupId)
    {
        return yield $this->redisPool->getCoroutine()->sMembers(Marco::redis_group_hash_name_prefix . $groupId);
    }

    /**
     * 广播
     * @param $data
     */
    public function sendToAll($data)
    {
        $data = $this->encode($this->pack->pack($data));
        $this->sendToDispatchMessage(Marco::MSG_TYPE_SEND_ALL, ['data' => $data]);
    }

    /**
     * 发送给群
     * @param $groupId
     * @param $data
     */
    public function sendToGroup($groupId, $data)
    {
        $data = $this->encode($this->pack->pack($data));
        $this->sendToDispatchMessage(Marco::MSG_TYPE_SEND_GROUP, ['data' => $data, 'groupId' => $groupId]);
    }

    /**
     * 向task发送中断信号
     * @param $taskId
     * @throws SwooleException
     */
    public function interruptedTask($taskId)
    {
        $ok = $this->taskLock->trylock();
        if ($ok) {
            getInstance()->tidPidTable->set(0, ['pid' => $taskId]);
            $taskPid = getInstance()->tidPidTable->get($taskId)['pid'];
            if ($taskPid == false) {
                $this->taskLock->unlock();
                throw new SwooleException('中断Task 失败，可能是task已运行完，或者task_id不存在。');
            }
            //发送信号
            posix_kill($taskPid, SIGUSR1);
            print_r("向TaskID=$taskId ,PID=$taskPid 的进程发送中断信号\n");
        } else {
            throw new SwooleException('interruptedTask 获得锁失败，中断操作正在进行请稍后。');
        }
    }

    /**
     * 获取服务器上正在运行的Task
     * @return array
     */
    public function getServerAllTaskMessage()
    {
        $tasks = [];
        foreach ($this->tidPidTable as $id => $row) {
            if ($id != 0) {
                $row['task_id'] = $id;
                $row['run_time'] = time() - $row['start_time'];
                $tasks[] = $row;
            }
        }
        return $tasks;
    }
}