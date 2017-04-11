<?php

/**
 * @desc: rpc控制器类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

use PG\MSF\Server\CoreBase\SwooleException;

class RpcController extends BaseController
{
    /**
     * @var bool
     */
    public $isRpc = true;
    /**
     * @var null
     */
    public $handler = null;
    /**
     * @var null
     */
    public $method = null;
    /**
     * @var null
     */
    public $version = null;
    /**
     * @var null
     */
    public $sig = null;
    /**
     * @var null
     */
    public $reqParams = null;

    /**
     * @var null
     */
    public $rpcTime = null;

    /**
     * @param string $controllerName
     * @param string $methodName
     */
    public function initialization($controllerName, $methodName)
    {
        parent::initialization($controllerName, $methodName);
    }

    /**
     * @param $arguments
     */
    public function httpCallHandler($arguments)
    {
        $this->parseHttpArgument($arguments);
        yield $this->runMethod();
    }

    /**
     * @param $arguments
     */
    public function tcpCallHandler($arguments)
    {
        $this->parseTcpArgument($arguments);
        yield $this->runMethod();
    }

    /**
     * @param $arguments
     * @throws SwooleException
     */
    protected function parseTcpArgument(&$arguments)
    {
        if (! is_array($arguments)) {
            throw new SwooleException('Rpc argument invalid.');
        }
        if (! isset($arguments['handler'])) {
            throw new SwooleException('Rpc argument of handler not set.');
        }
        if (! isset($arguments['method'])) {
            throw new SwooleException('Rpc argument of method not set.');
        }
        if (! isset($arguments['args'])) {
            throw new SwooleException('Rpc argument of args not set.');
        }
        $this->version = $arguments['version'] ?? null;
        $this->sig = $arguments['sig'] ?? null;
        $this->handler = $arguments['handler'];
        $this->method = $arguments['method'];
        $this->reqParams = (array)$arguments['args'];
        $this->rpcTime = $arguments['time'];
    }

    /**
     * @param $arguments
     * @throws SwooleException
     */
    protected function parseHttpArgument(&$arguments)
    {
        if (!is_array($arguments) || !isset($arguments['data']) || !isset($arguments['sig'])) {
            throw new SwooleException('Rpc argument invalid.');
        }
        if (!is_array($arguments['data'])) {
            $arguments['data'] = $this->pack->unPack($arguments['data']);
        }
        $arguments['data'] = (array)$arguments['data'];
        if (!isset($arguments['data']['handler'])) {
            throw new SwooleException('Rpc argument of handler not set.');
        }
        if (!isset($arguments['data']['method'])) {
            throw new SwooleException('Rpc argument of method not set.');
        }
        if (!isset($arguments['data']['args'])) {
            throw new SwooleException('Rpc argument of args not set.');
        }
        $this->version = $arguments['data']['version'] ?? null;
        $this->handler = $arguments['data']['handler'];
        $this->method = $arguments['data']['method'];
        $this->reqParams = (array)$arguments['data']['args'];
        $this->rpcTime = $arguments['data']['time'];
        $this->sig = $arguments['sig'];
    }

    /**
     * @throws SwooleException
     */
    protected function runMethod()
    {
        $handlerClass = 'Handlers\\' . $this->handler;
        $handlerInstance = $this->loader->model($handlerClass, $this);
        if (!method_exists($handlerInstance, $this->method)) {
            throw new SwooleException('Rpc method not found.');
        }
        //$response = $handlerInstance->{$this->method}(...$this->reqParams);
        $response = yield call_user_func_array([$handlerInstance, $this->method], $this->reqParams);
        
        $this->outputJson($response);
    }
}
