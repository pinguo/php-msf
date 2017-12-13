<?php
/**
 * MSFServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use Exception;
use PG\MSF\Helpers\Context;
use PG\MSF\Process\Config;
use PG\MSF\Process\Inotify;
use PG\MSF\Process\Timer;
use PG\MSF\Pools\AsynPool;
use PG\MSF\Pools\AsynPoolManager;
use PG\MSF\Pools\RedisAsynPool;
use PG\MSF\Pools\MysqlAsynPool;
use PG\MSF\Proxy\RedisProxyFactory;
use PG\MSF\Proxy\MysqlProxyFactory;
use PG\MSF\Proxy\IProxy;
use PG\MSF\Tasks\Task;
use PG\MSF\Base\AOPFactory;

/**
 * Class MSFServer
 * @package PG\MSF
 */
abstract class MSFServer extends HttpServer
{
    /**
     * 默认服务名称
     */
    const SERVER_NAME = 'SERVER';

    /**
     * @var array Redis代理管理器
     */
    protected $redisProxyManager = [];

    /**
     * @var array Mysql代理管理器
     */
    protected $mysqlProxyManager = [];

    /**
     * @var array 连接池
     */
    protected $asynPools = [];

    /**
     * @var AsynPoolManager 连接池管理器
     */
    protected $asynPoolManager;

    /**
     * @var array 自定义timer进程
     */
    protected $userTimerProcess = [];

    /**
     * @var array 自定义的timer任务
     */
    protected $userRegisterTimer = [];

    /**
     * @var int $taskLogRate task日志写入比例
     */
    private $taskLogRate = 100;

    /**
     * MSFServer constructor.
     */
    public function __construct()
    {
        self::$instance = $this;
        $this->name = self::SERVER_NAME;
        parent::__construct();
        //可配置task日志的写入比例 0表示不写，100表示全写，50表示50%的比例
        $this->taskLogRate = $this->config->get('server.log.task_log_rate', 100);
    }

    /**
     * 启动服务
     *
     * @return $this|void
     */
    public function start()
    {
        parent::start();
    }


    /**
     * 设置并解析配置
     *
     * @return $this
     */
    public function setConfig()
    {
        parent::setConfig();
    }

    /**
     * 服务启动前的初始化
     */
    public function beforeSwooleStart()
    {
        parent::beforeSwooleStart();

        // 初始化Yac共享内存
        $this->sysCache  = new \Yac('sys_cache_');

        //reload监控进程
        if ($this->config->get('auto_reload_enable', false)) {
            if (!extension_loaded('inotify')) {
                writeln("Inotify  Reload: Failed(未安装inotify扩展)");
            } else {
                $reloadProcess = new \swoole_process(function ($process) {
                    new Inotify($this->config, $this);
                    $this->onWorkerStart($this->server, null);
                }, false, 2);
                $this->server->addProcess($reloadProcess);
            }
        }

        //配置管理进程
        if ($this->config->get('config_manage_enable', false)) {
            $configProcess = new \swoole_process(function ($process) {
                new Config($this->config, $this);
                $this->onWorkerStart($this->server, null);
            }, false, 2);
            $this->server->addProcess($configProcess);
        }

        //业务自定义定时器进程
        $num = intval($this->config->get('user_timer_enable', 0));
        if ($num > 0) {
            $this->onInitTimer();
            //自定义多个定时器进程，但每个进程只有一个任务，且任务不同
            if (!empty($this->userTimerProcess)) {
                foreach ($this->userTimerProcess as $info) {
                    list($ms, $callBack, $params, $tickType) = $info;
                    $timerProcess = new \swoole_process(function ($process) use ($ms, $callBack, $params, $tickType) {
                        new Timer($this->config, $this);
                        $this->addTimer($ms, $callBack, $params, $tickType);
                        $this->onWorkerStart($this->server, null);
                    }, false, 2);
                    $this->server->addProcess($timerProcess);
                }
            }
            //自定义多个任务，也可以多进程，但每个进程的任务都相同
            if (!empty($this->userRegisterTimer)) {
                for ($i = 0; $i < $num; $i++) {
                    $timerProcess = new \swoole_process(function ($process) {
                        new Timer($this->config, $this);
                        foreach ($this->userRegisterTimer as $info) {
                            list($ms, $callBack, $params, $tickType) = $info;
                            $this->addTimer($ms, $callBack, $params, $tickType);
                        }
                        $this->onWorkerStart($this->server, null);
                    }, false, 2);
                    $this->server->addProcess($timerProcess);
                }
            }
        }
    }

    /**
     * 初始化连接池
     */
    public function initAsynPools()
    {
        $asynPools = [];

        if ($this->config->get('redis.active')) {
            $activePools = $this->config->get('redis.active');
            if (is_string($activePools)) {
                $activePools = explode(',', $activePools);
            }

            foreach ($activePools as $poolKey) {
                $asynPools[RedisAsynPool::ASYN_NAME . $poolKey] = new RedisAsynPool($this->config, $poolKey);
            }
        }

        if ($this->config->get('mysql.active')) {
            $activePools = $this->config->get('mysql.active');
            if (is_string($activePools)) {
                $activePools = explode(',', $activePools);
            }

            foreach ($activePools as $poolKey) {
                $asynPools[MysqlAsynPool::ASYN_NAME . $poolKey] = new MysqlAsynPool($this->config, $poolKey);
            }
        }

        $this->asynPools = $asynPools;
    }

    /**
     * 初始化redis代理客户端
     */
    public function initRedisProxies()
    {
        if ($this->config->get('redis_proxy.active')) {
            $activeProxies = $this->config->get('redis_proxy.active');
            if (is_string($activeProxies)) {
                $activeProxies = explode(',', $activeProxies);
            }

            foreach ($activeProxies as $activeProxy) {
                $this->redisProxyManager[$activeProxy] = RedisProxyFactory::makeProxy(
                    $activeProxy,
                    $this->config['redis_proxy'][$activeProxy]
                );
            }
        }
    }

    /**
     * 初始化mysql代理客户端
     */
    public function initMysqlProxies()
    {
        if ($this->config->get('mysql_proxy.active')) {
            $activeProxies = $this->config->get('mysql_proxy.active');
            if (is_string($activeProxies)) {
                $activeProxies = explode(',', $activeProxies);
            }

            foreach ($activeProxies as $activeProxy) {
                $this->mysqlProxyManager[$activeProxy] = MysqlProxyFactory::makeProxy(
                    $activeProxy,
                    $this->config['mysql_proxy'][$activeProxy]
                );
            }
        }
    }

    /**
     * 设置服务器配置参数
     *
     * @return array
     */
    public function setServerSet()
    {
        $set = $this->config->get('server.set', []);
        $this->workerNum = $set['worker_num'] ?? 0;
        $this->taskNum = $set['task_worker_num'] ?? 0;
        return $set;
    }

    /**
     * 异步Task任务回调
     *
     * @param \swoole_server $serv
     * @param int $taskId Task ID
     * @param int $fromId 来自于哪个worker进程
     * @param array $data 任务的内容
     * @return mixed|null
     * @throws Exception
     */
    public function onTask($serv, $taskId, $fromId, $data)
    {
        if (is_string($data)) {
            $unserializeData = unserialize($data);
        } else {
            $unserializeData = $data;
        }
        $type    = $unserializeData['type'] ?? '';
        $message = $unserializeData['message'] ?? '';
        $result  = false;
        $status  = 200;
        switch ($type) {
            case Macro::SERVER_TYPE_TASK:
                try {
                    $taskName      = $message['task_name'];
                    $taskFucName   = $message['task_fuc_name'];
                    $taskData      = $message['task_fuc_data'];
                    /**
                     * @var Context $taskContext
                     */
                    $taskContext   = $message['task_context'];
                    $taskConstruct = $message['task_construct'];

                    // 构造请求日志对象
                    $PGLog                            = clone getInstance()->log;
                    $PGLog->logId                     = $taskContext->getLogId();
                    $PGLog->accessRecord['beginTime'] = microtime(true);
                    $PGLog->accessRecord['uri']       = $taskContext->getInput()->getPathInfo();
                    $PGLog->pushLog('task', $taskName);
                    $PGLog->pushLog('method', $taskFucName);
                    defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME . '-task';
                    $PGLog->init();
                    $taskContext->setLogId($PGLog->logId);
                    $taskContext->setLog($PGLog);

                    if (empty($taskName) || empty($taskFucName)) {
                        $status = 500;
                        $PGLog->error('Task Not Found');
                        $PGLog->pushLog('status', $status);
                        $PGLog->appendNoticeLog();
                        return $result;
                    }

                    $objectPool    = AOPFactory::getObjectPool($this->objectPool, $this);
                    /**
                     * @var Task $task
                     */
                    $task          = $objectPool->get($taskName, $taskConstruct);

                    // 运行方法
                    if (method_exists($task, $taskFucName)) {
                        //给task做初始化操作
                        $task->__initialization($taskId, $this->server->worker_pid, $taskName, $taskFucName, $taskContext, $objectPool);
                        $result = $task->$taskFucName(...$taskData);
                    } else {
                        throw new Exception("method $taskFucName not exist in $taskName");
                    }
                } catch (\Throwable $e) {
                    $status = 500;
                    $PGLog->error(dump($e, false, true));
                } finally {
                    $PGLog->pushLog('status', $status);

                    if ($status === 200) {
                        (mt_rand(0, 99) < $this->taskLogRate) && $PGLog->appendNoticeLog();
                    } else {
                        $PGLog->appendNoticeLog();
                    }

                    //销毁对象
                    foreach ($this->objectPoolBuckets as $k => $obj) {
                        $objectPool->push($obj);
                        $this->objectPoolBuckets[$k] = null;
                        unset($this->objectPoolBuckets[$k]);
                    }
                }
                return $result;
            default:
                return parent::onTask($serv, $taskId, $fromId, $data);
        }
    }

    /**
     * PipeMessage
     *
     * @param \swoole_server $serv server实例
     * @param int $fromWorkerId worker id
     * @param string $message 消息内容
     */
    public function onPipeMessage($serv, $fromWorkerId, $message)
    {
        parent::onPipeMessage($serv, $fromWorkerId, $message);
        $data = unserialize($message);
        switch ($data['type']) {
            case Macro::MSG_TYPR_ASYN:
                $this->asynPoolManager->distribute($data['message']);
                break;
        }
    }

    /**
     * 手工添加AsynPool
     *
     * @param string $name 连接池名称
     * @param AsynPool $pool 连接池对象
     * @param bool $isRegister 是否注册到asynPoolManager
     * @throws Exception
     * @return $this
     */
    public function addAsynPool($name, AsynPool $pool, $isRegister = false)
    {
        if (key_exists($name, $this->asynPools)) {
            throw new Exception('pool key is exists!');
        }
        $this->asynPools[$name] = $pool;
        if ($isRegister && $this->asynPoolManager) {
            $pool->workerInit($this->server->worker_id ?? 0);
            $this->asynPoolManager->registerAsyn($pool);
        }

        return $this;
    }

    /**
     * 获取连接池
     *
     * @param string $name 连接池名称
     * @return AsynPool
     */
    public function getAsynPool($name)
    {
        return $this->asynPools[$name] ?? null;
    }

    /**
     * 手工添加redis代理
     *
     * @param string $name 代理名称
     * @param IProxy $proxy 代理实例
     * @throws Exception
     * @return $this
     */
    public function addRedisProxy($name, $proxy)
    {
        if (key_exists($name, $this->redisProxyManager)) {
            throw new Exception('proxy key is exists!');
        }
        $this->redisProxyManager[$name] = $proxy;

        return $this;
    }

    /**
     * 获取redis代理
     *
     * @param string $name 代理名称
     * @return mixed
     */
    public function getRedisProxy($name)
    {
        return $this->redisProxyManager[$name] ?? null;
    }

    /**
     * 设置redis代理
     *
     * @param string $name 代理名称
     * @param IProxy $proxy 代理实例
     * @return $this
     */
    public function setRedisProxy($name, $proxy)
    {
        $this->redisProxyManager[$name] = $proxy;
        return $this;
    }

    /**
     * 手工添加mysql代理
     *
     * @param string $name 代理名称
     * @param IProxy $proxy 代理实例
     * @throws Exception
     * @return $this
     */
    public function addMysqlProxy($name, $proxy)
    {
        if (key_exists($name, $this->mysqlProxyManager)) {
            throw new Exception('proxy key is exists!');
        }
        $this->mysqlProxyManager[$name] = $proxy;

        return $this;
    }

    /**
     * 获取mysql代理
     *
     * @param string $name 代理名称
     * @return mixed
     */
    public function getMysqlProxy($name)
    {
        return $this->mysqlProxyManager[$name] ?? null;
    }

    /**
     * 设置mysql代理
     *
     * @param string $name 代理名称
     * @param IProxy $proxy 代理实例
     * @return $this
     */
    public function setMysqlProxy($name, $proxy)
    {
        $this->mysqlProxyManager[$name] = $proxy;
        return $this;
    }

    /**
     * 获取所有的redisProxy
     *
     * @return array
     */
    public function &getRedisProxies()
    {
        return $this->redisProxyManager;
    }

    /**
     * 添加异步redis,添加redisProxy
     *
     * @param \swoole_server $serv server实例
     * @param int $workerId worker id
     * @throws Exception
     */
    public function onWorkerStart($serv, $workerId)
    {
        // Worker类型
        if (!$this->isTaskWorker()) {
            if ($workerId !== null) {
                $this->processType = Macro::PROCESS_WORKER;
                getInstance()->sysTimers[] = swoole_timer_tick(2000, function ($timerId) {
                    $this->statistics();
                });
            }
        } else {
            $this->processType = Macro::PROCESS_TASKER;
        }

        parent::onWorkerStart($serv, $workerId);
        if ($this->processType == Macro::PROCESS_WORKER || $this->processType == Macro::PROCESS_TIMER) {
            $this->initAsynPools();
            $this->initRedisProxies();
            $this->initMysqlProxies();

            //注册
            $this->asynPoolManager = new AsynPoolManager(null, $this);
            $this->asynPoolManager->noEventAdd();
            foreach ($this->asynPools as $pool) {
                if ($pool) {
                    $pool->workerInit($workerId);
                    $this->asynPoolManager->registerAsyn($pool);
                }
            }

            if (!empty($this->redisProxyManager)) {
                //redis proxy监测
                getInstance()->sysTimers[] = $this->server->tick(5000, function () {
                    foreach ($this->redisProxyManager as $proxy) {
                        $proxy->check();
                    }
                });
            }

            if (!empty($this->mysqlProxyManager)) {
                // mysql proxy监测
                getInstance()->sysTimers[] = $this->server->tick(5000, function () {
                    foreach ($this->mysqlProxyManager as $proxy) {
                        $proxy->check();
                    }
                });
            }
        }
    }

    /**
     * Worker进程统计信息
     *
     * @return array
     */
    public function statistics()
    {
        $data = [
            // 进程ID
            'pid' => 0,
            // 协程统计信息
            'coroutine' => [
                // 当前正在处理的请求数
                'total' => 0,
            ],
            // 内存使用
            'memory' => [
                // 峰值
                'peak' => '',
                // 当前使用
                'usage' => '',
            ],
            // 请求信息
            'request' => [
                // 当前Worker进程收到的请求次数
                'worker_request_count' => 0,
            ],
            // 其他对象池
            'object_pool' => [
                // 'xxx' => 22
            ],
            // Http DNS Cache
            'dns_cache_http' => [
                // domain => [ip, time(), times]
            ],
            // keep alive Cache
            'keep_alive_cache' => [
                // domain|ip|port => 10
            ],
            // exit
            'exit' => 0,
        ];
        $routineList = $this->scheduler->taskMap;
        $data['pid'] = $this->server->worker_pid;
        $data['coroutine']['total']   = count($routineList);
        $data['memory']['peak_byte']  = memory_get_peak_usage();
        $data['memory']['usage_byte'] = memory_get_usage();
        $data['memory']['peak']       = strval(number_format($data['memory']['peak_byte'] / 1024 / 1024, 3, '.', '')) . 'M';
        $data['memory']['usage']      = strval(number_format($data['memory']['usage_byte'] / 1024 / 1024, 3, '.', '')) . 'M';
        $data['request']['worker_request_count'] = $this->server->stats()['worker_request_count'];

        if (!empty($this->objectPool->map)) {
            foreach ($this->objectPool->map as $class => $objects) {
                $data['object_pool'][$class] = $objects->count();
            }

            /**
             * @var \PG\MSF\Coroutine\Task $task
             */
            foreach (getInstance()->scheduler->taskMap as $task) {
                foreach ($task->getController()->objectPoolBuckets as $object) {
                    $data['object_pool'][get_class($object)]++;
                }
                $data['object_pool'][get_class($task->getController())]++;
            }
        }

        foreach (\PG\MSF\Client\Http\Client::$keepAliveCache as $k => $items) {
            $data['keep_alive_cache'][$k] = count($items);
        }

        $data['dns_cache_http'] = \PG\MSF\Client\Http\Client::$dnsCache;
        $key  = Macro::SERVER_STATS . getInstance()->server->worker_id . '_exit';
        $data['exit'] = (int)$this->sysCache->get($key);
        $this->sysCache->set(Macro::SERVER_STATS . $this->server->worker_id, $data);

        return $data;
    }

    /**
     * 初始化自定义业务定时器（在独立进程中）
     *
     * @return mixed
     */
    public function onInitTimer()
    {
    }

    /**
     * 连接断开
     *
     * @param \swoole_server $serv
     * @param $fd
     */
    public function onClose($serv, $fd)
    {
        parent::onClose($serv, $fd);
    }

    /**
     * 获取正在运行的Task
     *
     * @return array
     */
    public function getAllTaskMessage()
    {
        return [];
    }

    /**
     * 添加并注册自定义的Timer进程和任务
     * 通常是一个进程一个任务
     * @param int $ms 定时器间隔
     * @param callable $callBack 回调函数
     * @param array $params 参数
     * @param string $tickType
     */
    public function addTimerProcess($ms, callable $callBack, $params = [], $tickType = Macro::SWOOLE_TIMER_TICK)
    {
        $this->userTimerProcess[] = [$ms, $callBack, $params, $tickType];
    }

    /**
     * 添加定时器
     * 通常是一个进程多个任务
     * @param int $ms
     * @param callable $callBack
     * @param array $params
     * @param callable|string $tickType
     */
    public function registerTimer($ms, callable $callBack, $params = [], $tickType = Macro::SWOOLE_TIMER_TICK)
    {
        $this->userRegisterTimer[] = [$ms, $callBack, $params, $tickType];
    }
}
