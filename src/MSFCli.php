<?php
/**
 * MSFServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use PG\MSF\Client\{
    Http\Client as HttpClient, Tcp\Client as TcpClient
};
use PG\MSF\Coroutine\{
    Task, GeneratorContext, Scheduler as Coroutine
};
use PG\MSF\DataBase\{
    AsynPool, AsynPoolManager, Miner, MysqlAsynPool, RedisAsynPool
};
use PG\MSF\Memory\Pool;
use PG\MSF\Proxy\RedisProxyFactory;
use PG\MSF\Base\Exception;
use PG\MSF\Console\{
    Request
};
use PG\MSF\Controllers\ControllerFactory;

class MSFCli extends WebSocketServer
{
    const SERVER_NAME = 'SERVER';
    /**
     * 运行方式（web/console）
     */
    const mode = 'console';
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
        self::$instance  = $this;
        $this->name      = self::SERVER_NAME;
        $this->coroutine = new Coroutine();
        parent::__construct();
    }

    public function onConsoleRequest()
    {
        parent::run();
        $request = new Request();
        $request->resolve();

        $controllerInstance = null;
        $this->route->handleClientRequest($request);

        $controllerName     = $this->route->getControllerName();
        $controllerInstance = ControllerFactory::getInstance()->getConsoleController($controllerName);
        if ($controllerInstance == null) {
            $controllerName = $this->route->getControllerName() . "\\" . $this->route->getMethodName();
            $controllerInstance = ControllerFactory::getInstance()->getConsoleController($controllerName);
            $this->route->setControllerName($controllerName);
        }

        if ($controllerInstance != null) {
            $methodName = $this->route->getMethodName();

            if (!method_exists($controllerInstance, $methodName)) {
                $methodName = 'index';
                $this->route->setMethodName($methodName);
            }

            $controllerInstance->setRequestResponse($request, null, $controllerName, $methodName);
            if (!method_exists($controllerInstance, $methodName)) {
                echo "not found method $controllerName->$methodName\n";
            } else {
                $generator = call_user_func([$controllerInstance, $methodName], $this->route->getParams());
                if ($generator instanceof \Generator) {
                    $generatorContext = new GeneratorContext();
                    $generatorContext->setController($controllerInstance, $controllerName, $methodName);
                    $controllerInstance->setGeneratorContext($generatorContext);
                    $this->coroutine->start($generator, $generatorContext);
                }
            }
        } else {
            echo "not found controller $controllerName\n";
        }
    }

    /**
     * 获取同步mysql
     * @return Miner
     * @throws Exception
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
     * 添加AsynPool
     * @param $name
     * @param AsynPool $pool
     * @throws Exception
     */
    public function addAsynPool($name, AsynPool $pool)
    {
        if (key_exists($name, $this->asynPools)) {
            throw new Exception('pool key is exists!');
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
     * @throws Exception
     */
    public function addRedisProxy($name, $proxy)
    {
        if (key_exists($name, $this->redisProxyManager)) {
            throw new Exception('proxy key is exists!');
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
     * @throws Exception
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        $this->initAsynPools();
        $this->initRedisProxies();
        $this->mysqlPool = $this->asynPools['mysqlPool'];
        //注册
        $this->asnyPoolManager = new AsynPoolManager($this->poolProcess, $this);
        $this->asnyPoolManager->noEventAdd();
        foreach ($this->asynPools as $pool) {
            if ($pool) {
                $pool->workerInit($workerId);
                $this->asnyPoolManager->registAsyn($pool);
            }
        }
        //初始化异步Client
        $this->client    = new HttpClient();
        $this->tcpClient = new TcpClient();

        //redis proxy监测
        if (!empty($this->redisProxyManager)) {
            foreach ($this->redisProxyManager as $proxy) {
                $proxy->check();
            }
        }
    }

    /**
     * 设置服务器配置参数
     * @return mixed
     */
    public function setServerSet()
    {
        // TODO: Implement setServerSet() method.
    }

    /**
     * Display staring UI.
     *
     * @return void
     */
    protected static function displayUI()
    {
        // TODO
    }

    /**
     * Parse command.
     *
     * @return void
     */
    protected static function parseCommand()
    {
        // TODO
    }

    /**
     * Init.
     *
     * @return void
     */
    protected static function init()
    {
        self::setProcessTitle(self::$_worker->config->get('server.process_title') . '-console');
    }

    /**
     * Init All worker instances.
     *
     * @return void
     */
    protected static function initWorkers()
    {
        // TODO
    }
}
