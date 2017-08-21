<?php
/**
 * MSFServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use Exception;
use PG\MSF\Process\Config;
use PG\MSF\Process\Inotify;
use PG\MSF\Process\Timer;
use PG\MSF\DataBase\AsynPool;
use PG\MSF\DataBase\AsynPoolManager;
use PG\MSF\Proxy\RedisProxyFactory;
use PG\MSF\Proxy\IProxy;
use PG\MSF\Tasks\Task;
use PG\MSF\Base\AOPFactory;

abstract class MSFServer extends HttpServer
{
    /**
     * 默认服务名称
     */
    const SERVER_NAME = 'SERVER';

    /**
     * var array Tasker进程内对象容器
     */
    public $objectPoolBuckets = [];

    /**
     * @var \swoole_process 连接池进程
     */
    protected $poolProcess;

    /**
     * @var array Redis代理管理器
     */
    protected $redisProxyManager = [];

    /**
     * @var \swoole_atomic task_id的原子
     */
    public $taskAtomic;

    /**
     * @var array task_id和pid的映射
     */
    public $tidPidTable;

    /**
     * @var array 连接池
     */
    protected $asynPools = [];

    /**
     * @var AsynPoolManager 连接池管理器
     */
    protected $asynPoolManager;

    /**
     * MSFServer constructor.
     */
    public function __construct()
    {
        self::$instance = $this;
        $this->name = self::SERVER_NAME;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    public function start()
    {
        parent::start();
    }


    /**
     * @inheritdoc
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

        //创建task用的Atomic
        $this->taskAtomic = new \swoole_atomic(0);

        //创建task用的id->pid共享内存表，进程最多可以同时处理8096个任务
        $this->tidPidTable = new \swoole_table(8096);
        $this->tidPidTable->column('pid',        \swoole_table::TYPE_INT, 8);
        $this->tidPidTable->column('des',        \swoole_table::TYPE_STRING, 50);
        $this->tidPidTable->column('start_time', \swoole_table::TYPE_INT, 8);
        $this->tidPidTable->create();

        //创建异步连接池进程
        if ($this->config->get('asyn_process_enable', false)) {
            $this->poolProcess = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-ASYN');
                $this->asynPoolManager = new AsynPoolManager($process, $this);
                $this->asynPoolManager->eventAdd();
                $this->initAsynPools();
                foreach ($this->asynPools as $pool) {
                    if ($pool) {
                        $this->asynPoolManager->registAsyn($pool);
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
                new Inotify($this->server);
            }, false, 2);
            $this->server->addProcess($reloadProcess);
        }

        //配置管理进程
        if ($this->config->get('config_manage_enable', false)) {
            $configProcess = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-CONFIG');
                new Config($this->config, $this);
            }, false, 2);
            $this->server->addProcess($configProcess);
        }

        //业务自定义定时器进程
        if ($this->config->get('user_timer_enable', false)) {
            $timerProcess = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-TIMER');
                new Timer($this->config, $this);
            }, false, 2);
            $this->server->addProcess($timerProcess);
        }
    }

    /**
     * 初始化连接池
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

        $this->asynPools = $redisPools;
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
                $this->redisProxyManager[$activeProxy] = RedisProxyFactory::makeProxy($activeProxy,
                    $this->config['redis_proxy'][$activeProxy]);
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
        $this->workerNum = $set['worker_num'];
        $this->taskNum = $set['task_worker_num'];
        return $set;
    }

    /**
     * 异步Task任务回调
     *
     * @param \swoole_server $serv
     * @param int $taskId
     * @param int $fromId
     * @param array $data
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
        switch ($type) {
            case Marco::SERVER_TYPE_TASK:
                try {
                    $taskName      = $message['task_name'];
                    $taskFucName   = $message['task_fuc_name'];
                    $taskData      = $message['task_fuc_data'];
                    $taskId        = $message['task_id'];
                    $taskContext   = $message['task_context'];
                    $taskConstruct = $message['task_construct'];
                    $objectPool    = AOPFactory::getObjectPool($this->objectPool, $this);
                    /**
                     * @var Task $task
                     */
                    $task          = $objectPool->get($taskName, ...$taskConstruct);

                    // 运行方法
                    if (method_exists($task, $taskFucName)) {
                        //给task做初始化操作
                        $task->__initialization($taskId, $this->server->worker_pid, $taskName, $taskFucName, $taskContext, $objectPool);
                        $result = $task->$taskFucName(...$taskData);
                    } else {
                        throw new Exception("method $taskFucName not exist in $taskName");
                    }

                    //销毁对象
                    foreach ($this->objectPoolBuckets as $k => $obj) {
                        $objectPool->push($obj);
                        $this->objectPoolBuckets[$k] = null;
                        unset($this->objectPoolBuckets[$k]);
                    }
                    $objectPool->setCurrentObjParent(null);
                } catch (\Throwable $e) {
                    if (empty($task) || !($task instanceof Task) || empty($task->getContext())) {
                        getInstance()->log->error(dump($e, false, true));
                    } else {
                        $error = dump($e, false, true);
                        $task->getContext()->getLog()->error($error);
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
     * @param \swoole_server $serv
     * @param int $fromWorkerId
     * @param string $message
     */
    public function onPipeMessage($serv, $fromWorkerId, $message)
    {
        parent::onPipeMessage($serv, $fromWorkerId, $message);
        $data = unserialize($message);
        switch ($data['type']) {
            case Marco::MSG_TYPR_ASYN:
                $this->asynPoolManager->distribute($data['message']);
                break;
        }
    }

    /**
     * 手工添加AsynPool
     *
     * @param string $name
     * @param AsynPool $pool
     * @param bool $isRegister
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
            $pool->workerInit($this->server->worker_id);
            $this->asynPoolManager->registAsyn($pool);
        }

        return $this;
    }

    /**
     * 获取连接池
     *
     * @param string $name
     * @return AsynPool
     */
    public function getAsynPool($name)
    {
        return $this->asynPools[$name] ?? null;
    }

    /**
     * 手工添加redis代理
     *
     * @param string $name
     * @param IProxy $proxy
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
     * @param string $name
     * @return mixed
     */
    public function getRedisProxy($name)
    {
        return $this->redisProxyManager[$name] ?? null;
    }

    /**
     * 设置redis代理
     *
     * @param string $name
     * @param IProxy $proxy
     * @return $this
     */
    public function setRedisProxy($name, $proxy)
    {
        $this->redisProxyManager[$name] = $proxy;
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
     * @param \swoole_server $serv
     * @param int $workerId
     * @throws Exception
     */
    public function onWorkerStart($serv, $workerId)
    {
        parent::onWorkerStart($serv, $workerId);
        $this->initAsynPools();
        $this->initRedisProxies();
        if (!$serv->taskworker && !empty($this->asynPools)) {
            //注册
            $this->asynPoolManager = new AsynPoolManager($this->poolProcess, $this);
            if (!$this->config['asyn_process_enable']) {
                $this->asynPoolManager->noEventAdd();
            }
            foreach ($this->asynPools as $pool) {
                if ($pool) {
                    $pool->workerInit($workerId);
                    $this->asynPoolManager->registAsyn($pool);
                }
            }
        } else {
            //注册中断信号
            pcntl_signal(SIGUSR1, function () {
            });
        }

        if (!empty($this->redisProxyManager)) {
            //redis proxy监测
            getInstance()->sysTimers[] = $this->server->tick(5000, function () {
                foreach ($this->redisProxyManager as $proxy) {
                    $proxy->check();
                }
            });
        }
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
