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
     * 是否开启路由缓存
     *
     * @var bool
     */
    public $enableCache = true;

    /**
     * 路由缓存
     *
     * @var array
     */
    public $routeCache;

    /**
     * @var \stdClass
     */
    protected $clientData;

    /**
     * 控制器完全命名空间类名
     *
     * @var string
     */
    public $controllerClassName;

    /**
     * NormalRoute constructor.
     */
    public function __construct()
    {
        $this->clientData = new \stdClass();
    }

    /**
     * 处理http request
     * @param $request
     */
    public function handleHttpRequest($request)
    {
        $this->clientData->path = rtrim($request->server['path_info'], '/');
        $this->clientData->verb = $this->parseVerb($request);
        $this->setParams($request->get ?? []);

        if (isset($request->header['x-rpc']) && $request->header['x-rpc'] == 1) {
            $this->clientData->isRpc          = true;
            $this->clientData->params         = $request->post ?? $request->get ?? [];
            $this->clientData->controllerName = getInstance()->config->get('rpc.default_controller');
            $this->clientData->methodName     = getInstance()->config->get('rpc.default_method');
        } else {
            $this->parsePath($this->clientData->path);
        }
    }

    public function setControllerClassName()
    {
        $this->controllerClassName = '';
        do {
            if (class_exists($this->clientData->controllerName)) {
                $this->controllerClassName = $this->clientData->controllerName;
                break;
            }

            $className = "\\App\\Controllers\\" . $this->clientData->controllerName;
            if (class_exists($className)) {
                $this->controllerClassName = $className;
                break;
            }

            $className = "\\PG\\MSF\\Controllers\\" . $this->clientData->controllerName;
            if (class_exists($className)) {
                $this->controllerClassName = $className;
                break;
            }
        } while (0);

        if ($this->controllerClassName  == '') {
            return false;
        }

        return true;
    }

    /**
     * 解析请求的URI
     *
     * @param $path
     */
    public function parsePath($path)
    {
        if ($this->getEnableCache() && isset($this->routeCache[$path])) {
            $this->clientData->controllerName = $this->routeCache[$path][0];
            $this->clientData->methodName     = $this->routeCache[$path][1];
            $this->controllerClassName        = $this->routeCache[$path][2];
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
            $this->controllerClassName        = '';

            if ($this->setControllerClassName()) {
                return true;
            }

            $methodDefault  = getInstance()->config->get('http.default_method', 'Index');
            $controllerName = $this->clientData->controllerName  . "\\" . $this->getMethodName();
            $this->setControllerName($controllerName);
            $this->setMethodName($methodDefault);

            if ($this->setControllerClassName()) {
                return true;
            }

            return false;
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
     * 获取请求对应的控制器完全命名空间类名
     */
    public function getControllerClassName()
    {
        return $this->controllerClassName;
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
