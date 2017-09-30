<?php
/**
 * RPC控制器，为RPC请求的入口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use Exception;

/**
 * Class Rpc
 * @package PG\MSF\Controllers
 */
class Rpc extends Controller
{
    /**
     * @var bool 是否为RPC请求
     */
    public $isRpc = true;
    /**
     * @var string|null handler名称
     */
    public $handler = null;

    /**
     * @var string|null handler执行方法名称
     */
    public $method = null;

    /**
     * @var string|null RPC请求版本
     */
    public $version = null;

    /**
     * @var array|null RPC请求参数
     */
    public $reqParams = null;

    /**
     * @var array|null handler构造参数
     */
    public $construct = null;

    /**
     * @var float|null 请求时间
     */
    public $rpcTime = null;

    /**
     * 构造方法
     *
     * @param string $controllerName controller标识
     * @param string $methodName method名称
     */
    public function __construct($controllerName, $methodName)
    {
        parent::__construct($controllerName, $methodName);
    }

    /**
     * RPC请求入口
     *
     * @param array $arguments
     * @return \Generator
     */
    public function actionIndex(...$arguments)
    {
        if ($this->getContext()->getInput()->getHeader('x-rpc') && isset($arguments[0])) {
            $this->parseHttpArgument($arguments);
            yield $this->runMethod();
        } else {
            $this->outputJson([], 400);
        }
    }

    /**
     * 解析RPC参数
     *
     * @param $arguments
     * @throws Exception
     */
    protected function parseHttpArgument($arguments)
    {
        if ($this->isRpc) {
            $unPackArgs = (array)getInstance()->pack->unPack($arguments[0]);
            if (!isset($unPackArgs['handler'])) {
                throw new Exception('Rpc argument of handler not set.');
            }
            if (!isset($unPackArgs['method'])) {
                throw new Exception('Rpc argument of method not set.');
            }
            if (!isset($unPackArgs['args'])) {
                throw new Exception('Rpc argument of args not set.');
            }
            $this->version   = $unPackArgs['version'] ?? null;
            $this->handler   = $unPackArgs['handler'];
            $this->method    = $unPackArgs['method'];
            $this->reqParams = (array)$unPackArgs['args'];
            $this->construct = (array)$unPackArgs['construct'];
            $this->rpcTime   = $unPackArgs['time'];
        }
    }

    /**
     * 执行
     *
     * @throws Exception
     */
    protected function runMethod()
    {
        $handlerClass    = '\\App\\Models\\Handlers\\' . $this->handler;
        $handlerInstance = $this->getObject($handlerClass, $this->construct);

        if (!method_exists($handlerInstance, $this->method)) {
            throw new Exception('Rpc method not found.');
        }

        $response = yield $handlerInstance->{$this->method}(...$this->reqParams);
        $this->getContext()->getLog()->pushLog('Rpc', [$handlerClass => $this->method]);
        $this->outputJson($response);
    }
}
