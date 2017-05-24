<?php
/**
 * SwooleWebSocketServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use PG\MSF\Controllers\ControllerFactory;
use PG\MSF\Helpers\Context;
use PG\MSF\Base\Input;
use PG\MSF\Base\Output;

abstract class WebSocketServer extends HttpServer
{
    /**
     * opcode
     * @var int
     */
    public $opcode;
    /**
     * websocket使能
     * @var bool
     */
    public $websocketEnable;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        parent::setConfig();
        $this->websocketEnable = $this->config->get('websocket.enable', false);
        $this->opcode = $this->config->get('websocket.opcode', WEBSOCKET_OPCODE_TEXT);
    }

    /**
     * 启动
     */
    public function start()
    {
        if (!$this->websocketEnable) {
            parent::start();
            return;
        }

        if (static::mode == 'console') {
            $this->beforeSwooleStart();
            $this->onSwooleWorkerStart(null, null);
        } else {
            //开启一个websocket服务器
            $this->server = new \swoole_websocket_server($this->httpSocketName, $this->httpPort);
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
            $this->server->on('open', [$this, 'onSwooleWSOpen']);
            $this->server->on('message', [$this, 'onSwooleWSMessage']);
            $this->server->on('close', [$this, 'onSwooleWSClose']);
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
     * 判断是tcp还是websocket进行发送
     * @param $fd
     * @param $data
     */
    public function send($fd, $data)
    {
        if (!$this->server->exist($fd)) {
            return;
        }
        if (!$this->websocketEnable) {
            parent::send($fd, $data);
            return;
        }
        if ($this->isWebSocket($fd)) {
            $data = substr($data, 4);
            $this->server->push($fd, $data, $this->opcode);
        } else {
            $this->server->send($fd, $data);
        }
    }

    /**
     * websocket连接上时
     * @param $server
     * @param $request
     */
    public function onSwooleWSOpen($server, $request)
    {
    }

    /**
     * websocket收到消息时
     * @param $server
     * @param $frame
     */
    public function onSwooleWSMessage($server, $frame)
    {
        $this->onSwooleWSAllMessage($server, $frame->fd, $frame->data);
    }

    /**
     * websocket合并后完整的消息
     * @param $serv
     * @param $fd
     * @param $data
     */
    public function onSwooleWSAllMessage($serv, $fd, $data)
    {
        $error = '';
        $code  = 500;

        //反序列化，出现异常断开连接
        try {
            $clientData = $this->pack->unPack($data);
        } catch (\Exception $e) {
            $serv->close($fd);
            return;
        }
        //client_data进行处理
        $clientData = $this->route->handleClientData($clientData);
        do {
            $controllerName     = $this->route->getControllerName();
            $controllerInstance = ControllerFactory::getInstance()->getController($controllerName);
            $methodPrefix       = $this->config->get('websocket.method_prefix', '');
            $methodDefault      = $this->config->get('websocket.default_method', 'Index');
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

            $uid = $serv->connection_info($fd)['uid'] ?? 0;
            try {
                $controllerInstance->context  = $controllerInstance->objectPool->get(Context::class);

                // 初始化控制器
                $controllerInstance->requestStartTime = microtime(true);
                $PGLog            = null;
                $PGLog            = clone $controllerInstance->getLogger();
                $PGLog->accessRecord['beginTime'] = $controllerInstance->requestStartTime;
                $PGLog->accessRecord['uri']       = $this->route->getPath();
                $PGLog->logId = $this->genLogId($clientData);
                defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME;
                $PGLog->init();
                $PGLog->pushLog('controller', $controllerName);
                $PGLog->pushLog('method', $methodName);

                // 构造请求上下文成员
                $controllerInstance->context->setLogId($PGLog->logId);
                $controllerInstance->context->setLog($PGLog);
                $controllerInstance->context->setObjectPool($controllerInstance->objectPool);
                $controllerInstance->setContext($controllerInstance->context);

                /**
                 * @var $input Input
                 */
                $input    = $controllerInstance->objectPool->get(Input::class);
                $input->set($clientData);
                /**
                 * @var $output Output
                 */
                $output   = $controllerInstance->objectPool->get(Output::class);
                $output->set($clientData, null);
                $output->initialization($controllerInstance);

                $controllerInstance->context->setInput($input);
                $controllerInstance->context->setOutput($output);
                $controllerInstance->context->setControllerName($controllerName);
                $controllerInstance->context->setActionName($methodName);

                $controllerInstance->setClientData($uid, $fd, $clientData, $controllerName, $methodName);

                $generator = call_user_func([$controllerInstance, $methodName], $this->route->getParams());
                if ($generator instanceof \Generator) {
                    $this->coroutine->start($generator, $controllerInstance->context, $controllerInstance);
                }

                if (!$this->route->getRouteCache($this->route->getPath())) {
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
                'data'       => self::$stdClass,
                'message'    => $error,
                'status'     => $code,
                'serverTime' => microtime(true)
            ]);
            $response = getInstance()->encode($this->pack->pack($res));
            getInstance()->send($fd, $response);
        }
    }

    /**
     * gen a logId
     *
     * @param $clientData
     * @return string
     */
    public function genLogId($clientData)
    {
        $logId = strval(new \MongoId());
        return $logId;
    }
    
    /**
     * websocket断开连接
     * @param $serv
     * @param $fd
     */
    public function onSwooleWSClose($serv, $fd)
    {
        $this->onSwooleClose($serv, $fd);
    }
}
