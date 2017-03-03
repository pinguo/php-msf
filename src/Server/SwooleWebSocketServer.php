<?php
/**
 * SwooleWebSocketServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server;

use PG\MSF\Server\CoreBase\ControllerFactory;
use PG\MSF\Server\CoreBase\GeneratorContext;

abstract class SwooleWebSocketServer extends SwooleHttpServer
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
    public $websocket_enable;

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
        $this->websocket_enable = $this->config->get('websocket.enable', false);
        $this->opcode = $this->config->get('websocket.opcode', WEBSOCKET_OPCODE_TEXT);
    }

    /**
     * 启动
     */
    public function start()
    {
        if (!$this->websocket_enable) {
            parent::start();
            return;
        }
        //开启一个websocket服务器
        $this->server = new \swoole_websocket_server($this->http_socket_name, $this->http_port);
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
        if ($this->tcp_enable) {
            $this->port = $this->server->listen($this->socket_name, $this->port, $this->socket_type);
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
     * 判断是tcp还是websocket进行发送
     * @param $fd
     * @param $data
     */
    public function send($fd, $data)
    {
        if (!$this->server->exist($fd)) {
            return;
        }
        if (!$this->websocket_enable) {
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
        //反序列化，出现异常断开连接
        try {
            $client_data = $this->pack->unPack($data);
        } catch (\Exception $e) {
            $serv->close($fd);
            return;
        }
        //client_data进行处理
        $client_data = $this->route->handleClientData($client_data);
        $controller_name = $this->route->getControllerName();
        $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
        if ($controller_instance != null) {
            $uid = $serv->connection_info($fd)['uid']??0;
            $method_name = $this->config->get('websocket.method_prefix', '') . $this->route->getMethodName();
            if (!method_exists($controller_instance, $method_name)) {
                $method_name = 'defaultMethod';
            }
            $controller_instance->setClientData($uid, $fd, $client_data, $controller_name, $method_name);
            try {
                $generator = call_user_func([$controller_instance, $method_name], $this->route->getParams());
                if ($generator instanceof \Generator) {
                    $generatorContext = new GeneratorContext();
                    $generatorContext->setController($controller_instance, $controller_name, $method_name);
                    $this->coroutine->start($generator, $generatorContext);
                }
            } catch (\Throwable $e) {
                call_user_func([$controller_instance, 'onExceptionHandle'], $e);
            }
        }
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