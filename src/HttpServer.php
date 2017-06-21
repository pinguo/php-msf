<?php
/**
 * http服务器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use League\Plates\Engine;
use PG\MSF\Controllers\ControllerFactory;
use PG\MSF\Helpers\Context;
use PG\MSF\Base\Input;
use PG\MSF\Base\Output;

abstract class HttpServer extends Server
{
    /**
     * http host
     * @var string
     */
    public $httpSocketName;
    /**
     * http port
     * @var integer
     */
    public $httpPort;
    /**
     * http使能
     * @var bool
     */
    public $httpEnable;
    /**
     * 模板引擎
     * @var Engine
     */
    public $templateEngine;

    public function __construct()
    {
        parent::__construct();
        //view dir
        $view_dir = APP_DIR . '/Views';
        if (!is_dir($view_dir)) {
            echo "App directory does not exist Views directory, please create.\n";
            exit();
        }
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        parent::setConfig();
        $this->httpEnable     = $this->config['http_server']['enable'];
        $this->httpSocketName = $this->config['http_server']['socket'];
        $this->httpPort       = $this->config['http_server']['port'];
    }

    /**
     * 启动
     */
    public function start()
    {
        if (!$this->httpEnable) {
            parent::start();
            return;
        }

        if (static::mode == 'console') {
            $this->beforeSwooleStart();
            $this->onSwooleWorkerStart(null, null);
        } else {
            //开启一个http服务器
            $this->server = new \swoole_http_server($this->httpSocketName, $this->httpPort);
            $this->server->on('Start', [$this, 'onSwooleStart']);
            $this->server->on('WorkerStart', [$this, 'onSwooleWorkerStart']);
            $this->server->on('WorkerStop', [$this, 'onSwooleWorkerStop']);
            $this->server->on('Task', [$this, 'onSwooleTask']);
            $this->server->on('Finish', [$this, 'onSwooleFinish']);
            $this->server->on('PipeMessage', [$this, 'onSwoolePipeMessage']);
            $this->server->on('WorkerError', [$this, 'onSwooleWorkerError']);
            $this->server->on('ManagerStart', [$this, 'onSwooleManagerStart']);
            $this->server->on('ManagerStop', [$this, 'onSwooleManagerStop']);
            $this->server->on('request', [$this, 'onSwooleRequest']);
            $set = $this->setServerSet();
            $set['daemonize'] = self::$daemonize ? 1 : 0;
            $this->server->set($set);
            if ($this->tcpEnable) {
                $this->port = $this->server->listen($this->socketName, $this->port, $this->socketType);
                $this->port->set($set);
                $this->port->on('connect', [$this, 'onSwooleConnect']);
                $this->port->on('receive', [$this, 'onSwooleReceive']);
                $this->port->on('close', [$this, 'onSwooleClose']);
                $this->port->on('Packet', [$this, 'onSwoolePacket']);
            }

            $this->beforeSwooleStart();
            $this->server->start();
        }
    }

    /**
     * workerStart
     * @param $serv
     * @param $workerId
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        $this->setTemplateEngine();
    }

    /**
     * 设置模板引擎
     */
    public function setTemplateEngine()
    {
        $this->templateEngine = new Engine();
        $this->templateEngine->registerFunction('getWww', 'getWww');
    }

    /**
     * http服务器发来消息
     * @param $request
     * @param $response
     */
    public function onSwooleRequest($request, $response)
    {
        $error              = '';
        $code               = 500;
        $controllerInstance = null;
        $this->route->handleClientRequest($request);

        do {
            if (!$this->route->getIsRpc() && $this->route->getPath() == '/') {
                list($host) = explode(':', $request->header['host'] ?? '');
                $wwwPath    = $this->getHostRoot($host) . $this->getHostIndex($host);
                $result     = httpEndFile($wwwPath, $request, $response);
                if (!$result) {
                    $error = 'Index not found';
                    $code  = 404;
                }
                break;
            }

            $controllerName     = $this->route->getControllerName();
            $controllerInstance = ControllerFactory::getInstance()->getController($controllerName);
            $methodPrefix       = $this->config->get('http.method_prefix', '');
            $methodDefault      = $this->config->get('http.default_method', 'Index');
            if ($controllerInstance == null) {
                $controllerName     = $controllerName . "\\" . $this->route->getMethodName();
                $controllerInstance = ControllerFactory::getInstance()->getController($controllerName);
                $this->route->setControllerName($controllerName);
                $methodName = $methodPrefix . $methodDefault;
                $this->route->setMethodName($methodDefault);
            } else {
                $methodName = $methodPrefix . $this->route->getMethodName();
            }

            if ($controllerInstance == null) {
                $error = 'Api not found controller(' . $controllerName . ')';
                $code  = 404;
                break;
            }

            if (!method_exists($controllerInstance, $methodName)) {
                $error = 'Api not found method(' . $methodName . ')';
                $code  = 404;
                break;
            }

            try {
                $controllerInstance->context  = $controllerInstance->getObjectPool()->get(Context::class);

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
                $input    = $controllerInstance->getObjectPool()->get(Input::class);
                $input->set($request);
                /**
                 * @var $output Output
                 */
                $output   = $controllerInstance->getObjectPool()->get(Output::class);
                $output->set($request, $response);
                $output->initialization($controllerInstance);

                $controllerInstance->context->setInput($input);
                $controllerInstance->context->setOutput($output);
                $controllerInstance->context->setControllerName($controllerName);
                $controllerInstance->context->setActionName($methodName);
                $controllerInstance->setRequestType(Marco::HTTP_REQUEST);
                $init = $controllerInstance->initialization($controllerName, $methodName);
                if ($init instanceof \Generator) {
                    $this->coroutine->start($init, $controllerInstance->context, $controllerInstance, function () use ($controllerInstance, $methodName) {
                        $generator = call_user_func([$controllerInstance, $methodName], $this->route->getParams());
                        if ($generator instanceof \Generator) {
                            $this->coroutine->taskMap[$controllerInstance->context->getLogId()]->resetRoutine($generator);
                            $this->coroutine->schedule($this->coroutine->taskMap[$controllerInstance->context->getLogId()]);
                        }
                    });
                } else {
                    $generator = call_user_func([$controllerInstance, $methodName], $this->route->getParams());
                    if ($generator instanceof \Generator) {
                        $this->coroutine->start($generator, $controllerInstance->context, $controllerInstance);
                    }
                }

                if ($this->route->getEnableCache() && !$this->route->getRouteCache($this->route->getPath())) {
                    $this->route->setRouteCache($this->route->getPath(), [$this->route->getControllerName(), $this->route->getMethodName()]);
                }
                break;
            } catch (\Throwable $e) {
                call_user_func([$controllerInstance, 'onExceptionHandle'], $e);
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
     * gen a logId
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

    /**
     * 获得host对应的根目录
     * @param $host
     * @return string
     */
    public function getHostRoot($host)
    {
        $rootPath = $this->config['http']['root'][$host]['root'] ?? '';
        if (!empty($rootPath)) {
            $rootPath = WWW_DIR . "/$rootPath/";
        } else {
            $rootPath = WWW_DIR . "/";
        }
        return $rootPath;
    }

    /**
     * 返回host对应的默认文件
     * @param $host
     * @return mixed|null
     */
    public function getHostIndex($host)
    {
        $index = $this->config['http']['root'][$host]['index'] ?? 'index.html';
        return $index;
    }
}
