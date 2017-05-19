<?php
/**
 * NormalRoute
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Route;

class NormalRoute implements IRoute
{
    /**
     * @var \stdClass
     */
    protected $clientData;

    /**
     * 路由缓存
     */
    public $routeCache;

    public function __construct()
    {
        $this->clientData = new \stdClass();
    }

    /**
     * 设置反序列化后的数据 Object
     * @param $data
     * @return \stdClass
     */
    public function handleClientData($data)
    {
        $this->clientData = $data;
        $this->parsePath($data->path);
        return $this->clientData;
    }

    /**
     * 解析path
     *
     * @param $path
     */
    public function parsePath($path)
    {
        if (isset($this->routeCache[$path])) {
            $this->clientData->controllerName = $this->routeCache[$path][0];
            $this->clientData->methodName     = $this->routeCache[$path][1];
        } else {
            $route = explode('/', $path);
            $route = array_map(function ($name) {
                $name = ucfirst($name);
                return $name;
            }, $route);
            $methodName = array_pop($route);
            $this->clientData->controllerName = ltrim(implode("\\", $route), "\\")??null;
            $this->clientData->methodName     = $methodName;
        }
    }

    /**
     * 处理http request
     * @param $request
     */
    public function handleClientRequest($request)
    {
        $this->clientData->path = rtrim($request->server['path_info'], '/');

        if (isset($request->header['x-rpc']) && $request->header['x-rpc'] == 1) {
            $this->clientData->isRpc          = true;
            $this->clientData->params         = $request->post ?? $request->get ?? [];
            $this->clientData->controllerName = getInstance()->config->get('rpc.default_controller');
            $this->clientData->methodName     = getInstance()->config->get('rpc.default_method');
        } else {
            $this->parsePath($this->clientData->path);
        }
    }

    /**
     * 获取控制器名称
     * @return string
     */
    public function getControllerName()
    {
        return $this->clientData->controllerName;
    }

    /**
     * 获取方法名称
     * @return string
     */
    public function getMethodName()
    {
        return $this->clientData->methodName;
    }

    public function getPath()
    {
        return $this->clientData->path;
    }

    public function getIsRpc()
    {
        return $this->clientData->isRpc??false;
    }

    public function getParams()
    {
        return $this->clientData->params??null;
    }

    public function setControllerName($name)
    {
        $this->clientData->controllerName = $name;
        return $this;
    }

    public function setMethodName($name)
    {
        $this->clientData->methodName = $name;
        return $this;
    }

    public function setParams($params)
    {
        $this->clientData->params = $params;
        return $this;
    }

    public function setRouteCache($path, $callable)
    {
        $this->routeCache[$path] = $callable;
        return $this;
    }

    public function getRouteCache($path)
    {
        return $this->routeCache[$path] ?? null;
    }
}
