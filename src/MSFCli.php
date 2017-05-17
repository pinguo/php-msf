<?php
/**
 * MSFServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use PG\MSF\Client\Http\Client as HttpClient;
use PG\MSF\Client\Tcp\Client as TcpClient;
use PG\MSF\DataBase\AsynPool;
use PG\MSF\DataBase\AsynPoolManager;
use PG\MSF\DataBase\Miner;
use PG\MSF\DataBase\MysqlAsynPool;
use PG\MSF\DataBase\RedisAsynPool;
use PG\MSF\Coroutine\Task;
use PG\MSF\Coroutine\Scheduler as Coroutine;
use PG\MSF\Console\Request;
use PG\MSF\Controllers\ControllerFactory;
use PG\MSF\Base\Exception;
use PG\MSF\Memory\Pool;
use PG\MSF\Base\Input;
use PG\MSF\Helpers\Context;

class MSFCli extends MSFServer
{
    /**
     * 运行方式（web/console）
     */
    const mode = 'console';

    /**
     * MSFServer constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->coroutine = new Coroutine();
    }

    public function onConsoleRequest()
    {
        parent::run();
        $request = new Request();
        $request->resolve();

        $controllerInstance = null;
        $this->route->handleClientRequest($request);

        do {
            $controllerName = $this->route->getControllerName();
            $controllerInstance = ControllerFactory::getInstance()->getConsoleController($controllerName);
            $methodDefault = $this->config->get('http.default_method', 'Index');
            if ($controllerInstance == null) {
                $controllerName = $controllerName . "\\" . $this->route->getMethodName();
                $controllerInstance = ControllerFactory::getInstance()->getConsoleController($controllerName);
                $this->route->setControllerName($controllerName);
                $methodName = $methodDefault;
                $this->route->setMethodName($methodDefault);
            } else {
                $methodName = $this->route->getMethodName();
            }

            if ($controllerInstance == null) {
                clearTimes();
                echo "not found controller $controllerName\n";
                break;
            }

            if (!method_exists($controllerInstance, $methodName)) {
                echo "not found method $controllerName->$methodName\n";
                $controllerInstance->destroy();
                break;
            }

            /**
             * @var $context Context
             */
            $context  = $controllerInstance->objectPool->get(Context::class);

            // 初始化控制器
            $controllerInstance->requestStartTime = microtime(true);
            $PGLog            = null;
            $PGLog            = clone $controllerInstance->logger;
            $PGLog->accessRecord['beginTime'] = $controllerInstance->requestStartTime;
            $PGLog->accessRecord['uri']       = $this->route->getPath();
            $PGLog->logId = $this->genLogId($request);
            defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME;
            $PGLog->init();
            $PGLog->pushLog('controller', $controllerName);
            $PGLog->pushLog('method', $methodName);

            // 构造请求上下文成员
            $context->setLogId($PGLog->logId);
            $context->setLog($PGLog);
            $context->setObjectPool($controllerInstance->objectPool);
            $controllerInstance->setContext($context);

            /**
             * @var $input Input
             */
            $input    = $controllerInstance->objectPool->get(Input::class);
            $input->set($request);
            $context->setInput($input);
            $context->setControllerName($controllerName);
            $context->setActionName($methodName);

            $controllerInstance->setRequestResponse($request, null, $controllerName, $methodName);

            $generator = call_user_func([$controllerInstance, $methodName], $this->route->getParams());
            if ($generator instanceof \Generator) {
                $this->coroutine->start($generator, $context, $controllerInstance);
            } else {
                $controllerInstance->destroy();
            }
            break;
        } while (0);
    }

    /**
     * gen a logId
     *
     * @param $request Request
     * @return string
     */
    public function genLogId($request)
    {
        $logId = strval(new \MongoId());
        return $logId;
    }

    /**
     * 开始前创建共享内存保存USID值
     */
    public function beforeSwooleStart()
    {
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
        getInstance()->sysTimers[] = swoole_timer_tick(5000, function () {
            if (!empty($this->redisProxyManager)) {
                foreach ($this->redisProxyManager as $proxy) {
                    $proxy->check();
                }
            }
        });
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
