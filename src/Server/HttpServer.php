<?php
/**
 * http服务器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server;

use League\Plates\Engine;
use PG\MSF\Server\{
    CoreBase\ControllerFactory, Coroutine\GeneratorContext
};

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
            echo "app目录下不存在Views目录，请创建。\n";
            exit();
        }
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        parent::setConfig();
        $this->httpEnable = $this->config['http_server']['enable'];
        $this->httpSocketName = $this->config['http_server']['socket'];
        $this->httpPort = $this->config['http_server']['port'];
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
        $this->templateEngine->addFolder('server', __DIR__ . '/Views');
        $this->templateEngine->addFolder('app', ROOT_PATH . '/app/Views');
        $this->templateEngine->registerFunction('get_www', 'get_www');
    }

    /**
     * http服务器发来消息
     * @param $request
     * @param $response
     */
    public function onSwooleRequest($request, $response)
    {
        $error = '';
        $controllerInstance = null;
        $this->route->handleClientRequest($request);
        list($host) = explode(':', $request->header['host']??'');
        if ($this->route->getPath() == '/') {
            $www_path = $this->getHostRoot($host) . $this->getHostIndex($host);
            $result = httpEndFile($www_path, $request, $response);
            if (!$result) {
                $error = 'index not found';
            } else {
                return;
            }
        } else {
            $controllerName = $this->route->getControllerName();
            $controllerInstance = ControllerFactory::getInstance()->getController($controllerName);
            if ($controllerInstance == null) {
                $controllerName = $this->route->getControllerName() . "\\" . $this->route->getMethodName();
                $controllerInstance = ControllerFactory::getInstance()->getController($controllerName);
                $this->route->setControllerName($controllerName);
            }

            if ($controllerInstance != null) {
                $methodName = $this->config->get('http.method_prefix', '') . $this->route->getMethodName();
                if (!method_exists($controllerInstance, $methodName)) {
                    $methodName = $this->config->get('http.method_prefix', '') . $this->config->get('http.default_method', 'Index');
                    $this->route->setMethodName($this->config->get('http.default_method', 'Index'));
                }

                try {
                    $controllerInstance->setRequestResponse($request, $response, $controllerName, $methodName);
                    if (!method_exists($controllerInstance, $methodName)) {
                        $error = 'api not found(action)';
                    } else {
                        $generator = call_user_func([$controllerInstance, $methodName], $this->route->getParams());
                        if ($generator instanceof \Generator) {
                            $generatorContext = new GeneratorContext();
                            $generatorContext->setController($controllerInstance, $controllerName, $methodName);
                            $controllerInstance->setGeneratorContext($generatorContext);
                            $this->coroutine->start($generator, $generatorContext);
                        }
                        return;
                    }
                } catch (\Throwable $e) {
                    call_user_func([$controllerInstance, 'onExceptionHandle'], $e);
                }
            } else {
                $error = 'api not found(controller)';
            }
        }
        
        if ($error) {
            if ($controllerInstance != null) {
                $controllerInstance->destroy();
            }

            $res = ['data' => [], 'message' => $error, 'status' => 500, 'serverTime' => microtime(true)];
            $response->end(json_encode($res));
        }
    }

    /**
     * 获得host对应的根目录
     * @param $host
     * @return string
     */
    public function getHostRoot($host)
    {
        $rootPath = $this->config['http']['root'][$host]['root']??'';
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
        $index = $this->config['http']['root'][$host]['index']??'index.html';
        return $index;
    }
}