<?php
/**
 * MSFServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use Exception;
use PG\MSF\Pools\AsynPoolManager;
use PG\MSF\Coroutine\Scheduler;
use PG\MSF\Console\Request;
use PG\MSF\Base\Pool;
use PG\MSF\Base\Input;
use PG\MSF\Helpers\Context;
use PG\MSF\Base\AOPFactory;

/**
 * Class MSFCli
 * @package PG\MSF
 */
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
        $this->scheduler = new Scheduler();
    }

    /**
     * 命令行请求回调
     */
    public function onConsoleRequest()
    {
        parent::run();
        $request = new Request();
        $request->resolve();

        $controllerInstance = null;
        $this->route->handleHttpRequest($request);

        do {
            $controllerName      = $this->route->getControllerName();
            $controllerClassName = $this->route->getControllerClassName();
            $methodPrefix        = $this->config->get('http.method_prefix', 'action');
            $methodName          = $methodPrefix . $this->route->getMethodName();
            if ($controllerClassName == '') {
                clearTimes();
                writeln("not found controller {$controllerName}");
                break;
            }

            /**
             * @var \PG\MSF\Controllers\Controller $controllerInstance
             */
            $controllerInstance = $this->objectPool->get($controllerClassName, [$controllerName, $methodName]);
            $controllerInstance->__useCount++;
            if (empty($controllerInstance->getObjectPool())) {
                $controllerInstance->setObjectPool(AOPFactory::getObjectPool(getInstance()->objectPool, $controllerInstance));
            }
            // 初始化控制器
            $controllerInstance->requestStartTime = microtime(true);
            if (!method_exists($controllerInstance, $methodName)) {
                writeln("not found method $controllerName" . "->" . "$methodName");
                $controllerInstance->destroy();
                break;
            }

            $controllerInstance->context  = $controllerInstance->getObjectPool()->get(Context::class);

            $PGLog            = null;
            $PGLog            = clone getInstance()->log;
            $PGLog->accessRecord['beginTime'] = $controllerInstance->requestStartTime;
            $PGLog->accessRecord['uri']       = $this->route->getPath();
            $PGLog->logId = $this->genLogId($request);
            defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME;
            $PGLog->init();
            $PGLog->pushLog('controller', $controllerName);
            $PGLog->pushLog('method', $methodName);

            // 构造请求上下文成员
            $controllerInstance->context->setLogId($PGLog->logId);
            $controllerInstance->context->setLog($PGLog);
            $controllerInstance->context->setObjectPool($controllerInstance->getObjectPool());

            /**
             * @var $input Input
             */
            $input    = $controllerInstance->getObjectPool()->get(Input::class);
            $input->set($request);
            $controllerInstance->context->setInput($input);
            $controllerInstance->context->setControllerName($controllerName);
            $controllerInstance->context->setActionName($methodName);
            $init = $controllerInstance->__construct($controllerName, $methodName);
            if ($init instanceof \Generator) {
                $this->scheduler->start(
                    $init,
                    $controllerInstance->context,
                    $controllerInstance,
                    function () use ($controllerInstance, $methodName) {
                        $params = array_values($this->route->getParams());
                        if (empty($this->route->getParams())) {
                            $params = [];
                        }

                        $generator = $controllerInstance->$methodName(...$params);
                        if ($generator instanceof \Generator) {
                            $this->scheduler->taskMap[$controllerInstance->context->getLogId()]->resetRoutine($generator);
                            $this->scheduler->taskMap[$controllerInstance->context->getLogId()]->resetCallBack(
                                function () use ($controllerInstance) {
                                    $controllerInstance->destroy();
                                }
                            );
                            $this->scheduler->schedule($this->scheduler->taskMap[$controllerInstance->context->getLogId()]);
                        }
                    }
                );
            } else {
                $params = array_values($this->route->getParams());
                if (empty($this->route->getParams())) {
                    $params = [];
                }

                $generator = $controllerInstance->$methodName(...$params);
                if ($generator instanceof \Generator) {
                    $this->scheduler->start(
                        $generator,
                        $controllerInstance->context,
                        $controllerInstance,
                        function () use ($controllerInstance) {
                            $controllerInstance->destroy();
                        }
                    );
                } else {
                    $controllerInstance->destroy();
                }
            }
            break;
        } while (0);
    }

    /**
     * gen a logId
     *
     * @param Request $request
     * @return string
     */
    public function genLogId($request)
    {
        $logId = strval(new \MongoId());
        return $logId;
    }

    /**
     * 服务启动前的初始化
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
     * 添加异步redis,添加redisProxy
     *
     * @param \swoole_server|null $serv server实例
     * @param int $workerId worker id
     * @throws Exception
     */
    public function onWorkerStart($serv, $workerId)
    {
        $this->initAsynPools();
        $this->initRedisProxies();
        //注册
        $this->asynPoolManager = new AsynPoolManager(null, $this);
        $this->asynPoolManager->noEventAdd();
        foreach ($this->asynPools as $pool) {
            if ($pool) {
                $pool->workerInit($workerId);
                $this->asynPoolManager->registerAsyn($pool);
            }
        }

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
