<?php
/**
 * MSFServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server;

use PG\MSF\Client\{
    Http\Client as HttpClient, Tcp\Client as TcpClient
};
use PG\MSF\Server\CoreBase\{
    ConfigProcess, InotifyProcess, SwooleException
};
use PG\MSF\Server\Coroutine\{
    CoroutineTask, GeneratorContext
};
use PG\MSF\Server\DataBase\{
    AsynPool, AsynPoolManager, Miner, MysqlAsynPool, RedisAsynPool
};
use PG\MSF\Server\Memory\Pool;
use PG\MSF\Server\Proxy\RedisProxyFactory;
use PG\MSF\Server\Test\TestModule;

abstract class MSFServer extends WebSocketServer
{
    const SERVER_NAME = 'SERVER';
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
     * 连接池进程
     * @var
     */
    protected $poolProcess;
    /**
     * 连接池
     * @var
     */
    protected $asynPools;
    /**
     * @var AsynPoolManager
     */
    private $asnyPoolManager;
    /**
     * @var array
     */
    private $redisProxyManager;

    /**
     * MSFServer constructor.
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
    }

    /**
     * 开始前创建共享内存保存USID值
     */
    public function beforeSwooleStart()
    {
        parent::beforeSwooleStart();

        // 初始化Yac共享内存
        $this->sysCache  = new \Yac('sys_cache_');

        //创建task用的Atomic
        $this->taskAtomic = new \swoole_atomic(0);

        //创建task用的id->pid共享内存表不至于同时超过1024个任务
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
                $this->initRedisProxies();
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

        //配置管理进程
        if ($this->config->get('config_manage_enable', false)) {
            $configProcess = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-CONFIG');
                new ConfigProcess($this->config, $this);
            }, false, 2);
            $this->server->addProcess($configProcess);
        }

        //初始化对象池
        $this->objectPool = Pool::getInstance();
    }

    /**
     * 初始化各种连接池
     */
    public function initAsynPools()
    {
        $redisPools = [];

        if ($this->config->get('redis.active')) {
            $activePools = $this->config->get('redis.active');
            if (is_string($activePools)) {
                $activePools = explode(',', $activePools);
            }

            foreach ($activePools as $poolKey) {
                $redisPools[$poolKey] = new RedisAsynPool($this->config, $poolKey);
            }
        }

        $this->asynPools = array_merge([
            'mysqlPool' => $this->config->get('database.active') ? new MysqlAsynPool($this->config,
                $this->config->get('database.active')) : null,
        ], $redisPools);
    }

    /**
     * 初始化redis代理客户端
     */
    public function initRedisProxies()
    {
        if ($this->config->get('redisProxy.active')) {
            $activeProxies = $this->config->get('redisProxy.active');
            if (is_string($activeProxies)) {
                $activeProxies = explode(',', $activeProxies);
            }

            foreach ($activeProxies as $activeProxy) {
                $this->redisProxyManager[$activeProxy] = RedisProxyFactory::makeProxy($activeProxy,
                    $this->config['redisProxy'][$activeProxy]);
            }
        }
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
        return $this->asynPools[$name] ?? null;
    }

    /**
     * 添加redis代理
     * @param $name
     * @param $proxy
     * @throws SwooleException
     */
    public function addRedisProxy($name, $proxy)
    {
        if (key_exists($name, $this->redisProxyManager)) {
            throw new SwooleException('proxy key is exists!');
        }
        $this->redisProxyManager[$name] = $proxy;
    }

    /**
     * 获取redis代理
     * @param $name
     * @return mixed
     */
    public function getRedisProxy($name)
    {
        return $this->redisProxyManager[$name] ?? null;
    }

    /**
     * 重新设置redis代理
     * @param $name
     * @param $proxy
     */
    public function setRedisProxy($name, $proxy)
    {
        $this->redisProxyManager[$name] = $proxy;
    }

    /**
     * 获取所有的redisProxy
     * @return array
     */
    public function &getRedisProxies()
    {
        return $this->redisProxyManager;
    }

    /**
     * 重写onSwooleWorkerStart方法，添加异步redis,添加redisProxy
     * @param $serv
     * @param $workerId
     * @throws SwooleException
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        $this->initAsynPools();
        $this->initRedisProxies();
        $this->mysqlPool = $this->asynPools['mysqlPool'];
        if (!$serv->taskworker && !empty($this->asynPools)) {
            //注册
            $this->asnyPoolManager = new AsynPoolManager($this->poolProcess, $this);
            if (!$this->config['asyn_process_enable']) {
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
    }

    /**
     * 开服初始化(支持协程)
     * @return mixed
     */
    public function onOpenServiceInitialization()
    {
    }

    /**
     * 连接断开
     * @param $serv
     * @param $fd
     */
    public function onSwooleClose($serv, $fd)
    {
        parent::onSwooleClose($serv, $fd);
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
