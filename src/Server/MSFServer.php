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
use PG\MSF\Server\{
    CoreBase\InotifyProcess, CoreBase\SwooleException, Coroutine\GeneratorContext
};
use PG\MSF\Server\Coroutine\CoroutineTask;
use PG\MSF\Server\DataBase\{
    AsynPool, AsynPoolManager, Miner, MysqlAsynPool, RedisAsynPool
};
use PG\MSF\Server\Memory\Pool;
use PG\MSF\Server\Test\TestModule;

abstract class MSFServer extends WebSocketServer
{
    const SERVER_NAME = "SERVER";
    /**
     * 实例
     * @var Server
     */
    private static $instance;

    /**
     * @var Pool
     */
    public $objectPool;

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
    protected $asynPools;

    /**
     * SwooleDistributedServer constructor.
     */
    public function __construct()
    {
        self::$instance = &$this;
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
        return parent::start();
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
                $this->asnyPoolManager->eventAdd();
                $this->initAsynPools();
                foreach ($this->asynPools as $pool) {
                    if ($pool) {
                        $this->asnyPoolManager->registAsyn($pool);
                    }
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
        //初始化对象池
        $this->objectPool = Pool::getInstance();
    }

    /**
     * 初始化各种连接池
     */
    public function initAsynPools()
    {
        $redisPoolNameBase = 'redisPool';
        $redisPools = [];

        if ($this->config->get('redis.active')) {
            $activePools = $this->config->get('redis.active');
            if (is_string($activePools)) {
                $activePools = explode(',', $activePools);
            }

            foreach ($activePools as $i => $poolKey) {
                if ($i === 0) {
                    $redisPools[$redisPoolNameBase] = new RedisAsynPool($this->config, $poolKey);
                } else {
                    $redisPools[$redisPoolNameBase . $i] = new RedisAsynPool($this->config, $poolKey);
                }
            }
        } else {
            $redisPools[$redisPoolNameBase] = null;
        }

        $this->asynPools = array_merge([
            'mysqlPool' => $this->config->get('database.active') ? new MysqlAsynPool($this->config,
                $this->config->get('database.active')) : null,
        ], $redisPools);
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
            throw new SwooleException('pool key is exists!');
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
        if (! $serv->taskworker) {
            //注册
            $this->asnyPoolManager = new AsynPoolManager($this->poolProcess, $this);
            if (! $this->config['asyn_process_enable']) {
                $this->asnyPoolManager->noEventAdd();
            }
            foreach ($this->asynPools as $pool) {
                if ($pool) {
                    $pool->workerInit($workerId);
                    $this->asnyPoolManager->registAsyn($pool);
                }
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
        if (! $this->isTaskWorker() && $this->initLock->trylock()) {
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
                if (! key_exists('start_time', $timerTask)) {
                    $startTime = -1;
                } else {
                    $startTime = strtotime(date($timerTask['start_time']));
                }
                if (! key_exists('end_time', $timerTask)) {
                    $endTime = -1;
                } else {
                    $endTime = strtotime(date($timerTask['end_time']));
                }
                if (! key_exists('delay', $timerTask)) {
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
                if (! empty($timerTask['task_name'])) {
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
        if (! empty($uid)) {
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