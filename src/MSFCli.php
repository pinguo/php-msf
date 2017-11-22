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
        $this->requestId++;
        parent::run();
        $request = new Request();
        $request->resolve();
        $this->route->handleHttpRequest($request);

        $PGLog            = clone getInstance()->log;
        $PGLog->accessRecord['beginTime'] = microtime(true);
        $PGLog->accessRecord['uri']       = $this->route->getPath();
        $PGLog->logId = $this->genLogId($request);
        defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME;
        $PGLog->init();
        $PGLog->pushLog('verb', 'cli');

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
             * @var \PG\MSF\Controllers\Controller $instance
             */
            $instance = $this->objectPool->get($controllerClassName, [$controllerName, $methodName]);
            $instance->__useCount++;
            if (empty($instance->getObjectPool())) {
                $instance->setObjectPool(AOPFactory::getObjectPool(getInstance()->objectPool, $instance));
            }
            // 初始化控制器
            $instance->requestStartTime = microtime(true);
            if (!method_exists($instance, $methodName)) {
                writeln("not found method $controllerName" . "->" . "$methodName");
                $instance->destroy();
                break;
            }

            $instance->context  = $instance->getObjectPool()->get(Context::class, [$this->requestId]);

            // 构造请求上下文成员
            $instance->context->setLogId($PGLog->logId);
            $instance->context->setLog($PGLog);
            $instance->context->setObjectPool($instance->getObjectPool());

            /**
             * @var $input Input
             */
            $input    = $instance->getObjectPool()->get(Input::class);
            $input->set($request);
            $instance->context->setInput($input);
            $instance->context->setControllerName($controllerName);
            $instance->context->setActionName($methodName);
            $init = $instance->__construct($controllerName, $methodName);
            if ($init instanceof \Generator) {
                $this->scheduler->start(
                    $init,
                    $instance,
                    function () use ($instance, $methodName) {
                        $params = array_values($this->route->getParams());
                        if (empty($this->route->getParams())) {
                            $params = [];
                        }

                        $generator = $instance->$methodName(...$params);
                        if ($generator instanceof \Generator) {
                            $this->scheduler->taskMap[$instance->context->getRequestId()]->resetRoutine($generator);
                            $this->scheduler->schedule(
                                $this->scheduler->taskMap[$instance->context->getRequestId()],
                                function () use ($instance) {
                                    $instance->destroy();
                                }
                            );
                        } else {
                            $instance->destroy();
                        }
                    }
                );
            } else {
                $params = array_values($this->route->getParams());
                if (empty($this->route->getParams())) {
                    $params = [];
                }

                $generator = $instance->$methodName(...$params);
                if ($generator instanceof \Generator) {
                    $this->scheduler->start(
                        $generator,
                        $instance,
                        function () use ($instance) {
                            $instance->destroy();
                        }
                    );
                } else {
                    $instance->destroy();
                }
            }
            break;
        } while (0);
    }

    /**
     * 服务启动前的初始化
     */
    public function beforeSwooleStart()
    {
        // 初始化Yac共享内存
        $this->sysCache  = new \Yac('sys_cache_');

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
