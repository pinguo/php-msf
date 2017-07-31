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
     * @var bool
     */
    public $enableCache = true;
    /**
     * 路由缓存
     */
    public $routeCache;
    /**
     * @var \stdClass
     */
    protected $clientData;

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
     * 处理http request
     * @param $request
     */
    public function handleClientRequest($request)
    {
        $this->clientData->path = rtrim($request->server['path_info'], '/');
        $this->clientData->verb = $this->parseVerb($request);
        $this->setParams($request->get);

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
     * 解析path
     *
     * @param $path
     */
    public function parsePath($path)
    {
        if ($this->getEnableCache() && isset($this->routeCache[$path])) {
            $this->clientData->controllerName = $this->routeCache[$path][0];
            $this->clientData->methodName     = $this->routeCache[$path][1];
        } else {
            $route = explode('/', $path);
            $route = array_map(function ($name) {
                if (strpos($name, '-') !== false) { // 中横线模式处理.
                    $slices = array_map('ucfirst', explode('-', $name));
                    $name = '';
                    foreach ($slices as $slice) {
                        $name .= $slice;
                    }
                } else {
                    $name = ucfirst($name);
                }
                return $name;
            }, $route);
            $methodName = array_pop($route);
            $this->clientData->controllerName = ltrim(implode("\\", $route), "\\")??null;
            $this->clientData->methodName     = $methodName;
        }
    }

    /**
     * @param $request
     * @return string
     */
    public function parseVerb($request)
    {
        if (isset($request->server['http_x_http_method_override'])) {
            return strtoupper($request->server['http_x_http_method_override']);
        }
        if (isset($request->server['request_method'])) {
            return strtoupper($request->server['request_method']);
        }

        return 'GET';
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
        return $this->clientData->isRpc ?? false;
    }

    public function getVerb()
    {
        return $this->clientData->verb ?? null;
    }

    public function getParams()
    {
        return $this->clientData->params ?? [];
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

    public function getEnableCache()
    {
        return $this->enableCache;
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
