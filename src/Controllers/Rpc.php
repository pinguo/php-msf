<?php
/**
 * RPC控制器，为RPC请求的入口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use Exception;

class Rpc extends Controller
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
     * @var null | array
     */
    public $reqParams = null;

    /**
     * @var null | array
     */
    public $methodParams = null;
    /**
     * @var null
     */
    public $rpcTime = null;

    /**
     * @var array 参数反射缓存
     */
    protected static $reflectionParameterCache = [];

    /**
     * 构造方法
     *
     * @param string $controllerName
     * @param string $methodName
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
    public function actionIndex($arguments)
    {
        $this->parseHttpArgument($arguments);
        yield $this->runMethod();
    }

    /**
     * @param $arguments
     * @throws Exception
     */
    protected function parseHttpArgument(&$arguments)
    {
        if (!is_array($arguments) || !isset($arguments['data'])) {
            throw new Exception('Rpc argument invalid.');
        }
        if (!is_array($arguments['data'])) {
            $arguments['data'] = getInstance()->pack->unPack($arguments['data']);
        }
        $arguments['data'] = (array)$arguments['data'];
        if (!isset($arguments['data']['handler'])) {
            throw new Exception('Rpc argument of handler not set.');
        }
        if (!isset($arguments['data']['method'])) {
            throw new Exception('Rpc argument of method not set.');
        }
        if (!isset($arguments['data']['args'])) {
            throw new Exception('Rpc argument of args not set.');
        }
        $this->version = $arguments['data']['version'] ?? null;
        $this->handler = $arguments['data']['handler'];
        $this->method = $arguments['data']['method'];
        $this->reqParams = (array)$arguments['data']['args'];
        $this->rpcTime = $arguments['data']['time'];
    }

    /**
     * @param $arguments
     * @throws Exception
     */
    protected function parseTcpArgument(&$arguments)
    {
        if (!is_array($arguments)) {
            throw new Exception('Rpc argument invalid.');
        }
        if (!isset($arguments['handler'])) {
            throw new Exception('Rpc argument of handler not set.');
        }
        if (!isset($arguments['method'])) {
            throw new Exception('Rpc argument of method not set.');
        }
        if (!isset($arguments['args'])) {
            throw new Exception('Rpc argument of args not set.');
        }
        $this->version = $arguments['version'] ?? null;
        $this->handler = $arguments['handler'];
        $this->method = $arguments['method'];
        $this->reqParams = (array)$arguments['args'];
        $this->rpcTime = $arguments['time'];
    }

    /**
     * @throws Exception
     */
    protected function runMethod()
    {
        $handlerClass = 'Handlers\\' . $this->handler;
        $handlerInstance = $this->getLoader()->model($handlerClass, $this);
        if (!method_exists($handlerInstance, $this->method)) {
            throw new Exception('Rpc method not found.');
        }
        //$response = $handlerInstance->{$this->method}(...$this->reqParams);
        $this->preRunMethod();
        $response = yield $handlerInstance->{$this->method}(...array_values($this->methodParams ?? $this->reqParams));

        $this->outputJson($response);
    }

    /**
     * 在执行 method 前执行
     */
    protected function preRunMethod()
    {
        $key = 'rpc_' . $this->handler . '::' . $this->method;
        $parameters = getInstance()->sysCache->get($key);
        if (false === $parameters) {
            $handlerClass = '\\App\\Models\\Handlers\\' . $this->handler;
            $reflection = new \ReflectionMethod($handlerClass, $this->method);
            $params = [];
            foreach ($reflection->getParameters() as $reflectionParameter) {
                $defaultValue = null;
                if ($reflectionParameter->isOptional()) {
                    $defaultValue = $reflectionParameter->getDefaultValue();
                }
                $params[$reflectionParameter->name] = $defaultValue;
            };
            getInstance()->sysCache->set($key, $params);
            $parameters = $params;
        }

        foreach ($parameters as $name => $val) {
            if (isset($this->reqParams[$name])) {
                $parameters[$name] = $this->reqParams[$name];
            }
        }

        $this->methodParams = $parameters;
    }

    public function destroy()
    {
        parent::destroy();
    }
}
