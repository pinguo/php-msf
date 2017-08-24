<?php
/**
 * http服务器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use League\Plates\Engine;
use PG\MSF\Helpers\Context;
use PG\MSF\Base\Input;
use PG\MSF\Base\Output;
use PG\MSF\Base\AOPFactory;

abstract class HttpServer extends Server
{
    /**
     * @var string HTTP服务监听地址如: 0.0.0.0
     */
    public $httpSocketName;

    /**
     * @var integer HTTP服务监听商品
     */
    public $httpPort;

    /**
     * @var bool 是否启用HTTP服务
     */
    public $httpEnable;

    /**
     * @var Engine 内置模板引擎
     */
    public $templateEngine;

    /**
     * HttpServer constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $view_dir = APP_DIR . '/Views';
        if (!is_dir($view_dir)) {
            writeln('App', 'App directory does not exist Views directory, please create.');
            exit();
        }
    }

    /**
     * 设置并解析配置
     *
     * @return $this
     */
    public function setConfig()
    {
        parent::setConfig();
        $this->httpEnable     = $this->config['http_server']['enable'];
        $this->httpSocketName = $this->config['http_server']['socket'];
        $this->httpPort       = $this->config['http_server']['port'];
        return $this;
    }

    /**
     * 启动服务
     *
     * @return $this|void
     */
    public function start()
    {
        if (!$this->httpEnable) {
            parent::start();
            return;
        }

        if (static::mode == 'console') {
            $this->beforeSwooleStart();
            $this->onWorkerStart(null, null);
        } else {
            //开启一个http服务器
            $this->server = new \swoole_http_server($this->httpSocketName, $this->httpPort);
            $this->server->on('Start', [$this, 'onStart']);
            $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
            $this->server->on('WorkerStop', [$this, 'onWorkerStop']);
            $this->server->on('Task', [$this, 'onTask']);
            $this->server->on('Finish', [$this, 'onFinish']);
            $this->server->on('PipeMessage', [$this, 'onPipeMessage']);
            $this->server->on('WorkerError', [$this, 'onWorkerError']);
            $this->server->on('ManagerStart', [$this, 'onManagerStart']);
            $this->server->on('ManagerStop', [$this, 'onManagerStop']);
            $this->server->on('request', [$this, 'onRequest']);
            $set = $this->setServerSet();
            $set['daemonize'] = self::$daemonize ? 1 : 0;
            $this->server->set($set);
            $this->beforeSwooleStart();
            $this->server->start();
        }

        return $this;
    }

    /**
     * Swoole Worker进程启动回调
     *
     * @param \swoole_server $serv
     * @param $workerId
     */
    public function onWorkerStart($serv, $workerId)
    {
        parent::onWorkerStart($serv, $workerId);
        $this->setTemplateEngine();
    }

    /**
     * 设置模板引擎
     *
     * @return $this
     */
    public function setTemplateEngine()
    {
        $this->templateEngine = new Engine();
        return $this;
    }

    /**
     * HTTP请求回调
     *
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    public function onRequest($request, $response)
    {
        $error              = '';
        $code               = 500;
        $controllerInstance = null;
        $this->route->handleHttpRequest($request);

        do {
            if ($this->route->getPath() == '') {
                $error = 'Index not found';
                $code  = 404;
                break;
            }

            $controllerName      = $this->route->getControllerName();
            $controllerClassName = $this->route->getControllerClassName();
            if ($controllerClassName == '') {
                $error = 'Api not found controller(' . $controllerName . ')';
                $code  = 404;
                break;
            }

            $methodPrefix = $this->config->get('http.method_prefix', 'action');
            $methodName   = $methodPrefix . $this->route->getMethodName();

            try {
                /**
                 * @var \PG\MSF\Controllers\Controller $controllerInstance
                 */
                $controllerInstance = $this->objectPool->get($controllerClassName, [$controllerName, $methodName]);
                $controllerInstance->__useCount++;
                if (empty($controllerInstance->getObjectPool())) {
                    $controllerInstance->setObjectPool(AOPFactory::getObjectPool(getInstance()->objectPool, $controllerInstance));
                }

                if (!method_exists($controllerInstance, $methodName)) {
                    $error = 'Api not found method(' . $methodName . ')';
                    $code  = 404;
                    break;
                }

                $controllerInstance->context = $controllerInstance->getObjectPool()->get(Context::class);

                // 初始化控制器
                $controllerInstance->requestStartTime = microtime(true);
                $PGLog            = null;
                $PGLog            = clone getInstance()->log;
                $PGLog->accessRecord['beginTime'] = $controllerInstance->requestStartTime;
                $PGLog->accessRecord['uri']       = $this->route->getPath();
                $PGLog->logId = $this->genLogId($request);
                defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME;
                $PGLog->init();
                $PGLog->pushLog('controller', $controllerName);
                $PGLog->pushLog('method', $methodName);
                $PGLog->pushLog('verb', $this->route->getVerb());

                // 构造请求上下文成员
                $controllerInstance->context->setLogId($PGLog->logId);
                $controllerInstance->context->setLog($PGLog);
                $controllerInstance->context->setObjectPool($controllerInstance->getObjectPool());

                /**
                 * @var $input Input
                 */
                $input    = $controllerInstance->context->getObjectPool()->get(Input::class);
                $input->set($request);
                /**
                 * @var $output Output
                 */
                $output   = $controllerInstance->context->getObjectPool()->get(Output::class, [$controllerInstance]);
                $output->set($request, $response);

                $controllerInstance->context->setInput($input);
                $controllerInstance->context->setOutput($output);
                $controllerInstance->context->setControllerName($controllerName);
                $controllerInstance->context->setActionName($methodName);
                $controllerInstance->setRequestType(Marco::HTTP_REQUEST);
                $init = $controllerInstance->__construct($controllerName, $methodName);

                if ($init instanceof \Generator) {
                    $this->scheduler->start(
                        $init,
                        $controllerInstance->context,
                        $controllerInstance,
                        function () use ($controllerInstance, $methodName) {
                            $generator = $controllerInstance->$methodName(...array_values($this->route->getParams()));
                            if ($generator instanceof \Generator) {
                                $this->scheduler->taskMap[$controllerInstance->context->getLogId()]->resetRoutine($generator);
                                $this->scheduler->schedule($this->scheduler->taskMap[$controllerInstance->context->getLogId()]);
                            }
                        });
                } else {
                    $generator = $controllerInstance->$methodName(...array_values($this->route->getParams()));
                    if ($generator instanceof \Generator) {
                        $this->scheduler->start($generator, $controllerInstance->context, $controllerInstance);
                    }
                }

                if ($this->route->getEnableCache() && !$this->route->getRouteCache($this->route->getPath())) {
                    $this->route->setRouteCache(
                        $this->route->getPath(),
                        [$controllerName, $this->route->getMethodName(), $controllerClassName]
                    );
                }
                break;
            } catch (\Throwable $e) {
                writeln('App', dump($e, true, true));
                $controllerInstance->onExceptionHandle($e);
            }
        } while (0);

        if ($error !== '') {
            if ($controllerInstance != null) {
                $controllerInstance->destroy();
            }

            $res = json_encode([
                'data'       => parent::$stdClass,
                'message'    => $error,
                'status'     => $code,
                'serverTime' => microtime(true)
            ]);
            $response->end($res);
        }
    }

    /**
     * 产生日志ID
     *
     * @param \swoole_http_request $request
     * @return string
     */
    public function genLogId($request)
    {
        $logId = $request->header['log_id'] ?? '' ;

        if (!$logId) {
            $logId = strval(new \MongoId());
        }

        return $logId;
    }
}
