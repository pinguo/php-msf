<?php
/**
 * NormalRoute
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Route;

/**
 * Class NormalRoute
 * @package PG\MSF\Route
 */
class NormalRoute implements IRoute
{
    /**
     * @var bool 是否开启路由缓存
     */
    public $enableCache = true;

    /**
     * @var array 路由缓存
     */
    public $routeCache;

    /**
     * @var \stdClass 请求的路由相关信息
     */
    protected $routePrams;

    /**
     * @var string 控制器完全命名空间类名
     */
    public $controllerClassName;

    /**
     * NormalRoute constructor.
     */
    public function __construct()
    {
        $this->routePrams = new \stdClass();
    }

    /**
     * HTTP请求解析
     *
     * @param \swoole_http_client $request 请求对象
     */
    public function handleHttpRequest($request)
    {
        $this->routePrams->path = rtrim($request->server['path_info'], '/');
        $this->routePrams->verb = $this->parseVerb($request);
        $this->setParams($request->get ?? []);

        if (isset($request->header['x-rpc']) && $request->header['x-rpc'] == 1) {
            $this->routePrams->isRpc          = true;
            $this->routePrams->params         = $request->post ?? $request->get ?? [];
            $this->routePrams->controllerName = 'Rpc';
            $this->routePrams->methodName     = 'Index';
            $this->controllerClassName        = '\PG\MSF\Controllers\Rpc';
            $this->routePrams->path           = '/Rpc/Index';
        } else {
            $this->parsePath($this->routePrams->path);
        }
    }

    /**
     * 计算Controller Class Name
     *
     * @return bool
     */
    public function findControllerClassName()
    {
        $this->controllerClassName = '';
        do {
            $className = "\\App\\Controllers\\" . $this->routePrams->controllerName;
            if (class_exists($className)) {
                $this->controllerClassName = $className;
                break;
            }

            $className = "\\PG\\MSF\\Controllers\\" . $this->routePrams->controllerName;
            if (class_exists($className)) {
                $this->controllerClassName = $className;
                break;
            }

            $className = "\\App\\Console\\" . $this->routePrams->controllerName;
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
     * 解析请求的URL PATH
     *
     * @param string $path 待解析URL Path
     * @return bool
     */
    public function parsePath($path)
    {
        if ($this->getEnableCache() && isset($this->routeCache[$path])) {
            $this->routePrams->controllerName = $this->routeCache[$path][0];
            $this->routePrams->methodName     = $this->routeCache[$path][1];
            $this->controllerClassName        = $this->routeCache[$path][2];
        } else {
            $route = explode('/', ltrim($path, '/'));
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
            if (count($route) > 1) {
                $methodName = array_pop($route);
            } else {
                $methodName = getInstance()->config->get('http.default_method', 'Index');
            }
            $this->routePrams->controllerName = ltrim(implode("\\", $route), "\\") ?? null;
            $this->routePrams->methodName     = $methodName;
            $this->controllerClassName        = '';

            if ($this->findControllerClassName()) {
                return true;
            }

            $methodDefault  = getInstance()->config->get('http.default_method', 'Index');
            $controllerName = $this->routePrams->controllerName  . "\\" . $this->getMethodName();
            $this->setControllerName($controllerName);
            $this->setMethodName($methodDefault);

            if ($this->findControllerClassName()) {
                return true;
            }

            return false;
        }
    }

    /**
     * 解析请求的方法
     *
     * @param \swoole_http_request $request 请求对象
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
     *
     * @return string
     */
    public function getControllerName()
    {
        return $this->routePrams->controllerName;
    }

    /**
     * 获取请求对应的控制器完全命名空间类名
     *
     * @return string
     */
    public function getControllerClassName()
    {
        return $this->controllerClassName;
    }

    /**
     * 获取方法名称
     *
     * @return string
     */
    public function getMethodName()
    {
        return $this->routePrams->methodName;
    }

    /**
     * 获取请求的PATH
     *
     * @return string
     */
    public function getPath()
    {
        return $this->routePrams->path;
    }

    /**
     * 判断请求是否为RPC请求
     *
     * @return bool
     */
    public function getIsRpc()
    {
        return $this->routePrams->isRpc ?? false;
    }

    /**
     * 获取请求的方法
     *
     * @return string|null
     */
    public function getVerb()
    {
        return $this->routePrams->verb ?? null;
    }

    /**
     * 获取请求的参数
     *
     * @return array
     */
    public function getParams()
    {
        return $this->routePrams->params ?? [];
    }

    /**
     * 设置请求的控制器标识
     *
     * @param string $name 控制器标识
     * @return $this
     */
    public function setControllerName($name)
    {
        $this->routePrams->controllerName = $name;
        return $this;
    }

    /**
     * 设置请求控制器的方法标识
     *
     * @param string $name 控制器的方法标识
     * @return $this
     */
    public function setMethodName($name)
    {
        $this->routePrams->methodName = $name;
        return $this;
    }

    /**
     * 设置请求的参数
     *
     * @param array $params 请求的参数
     * @return $this
     */
    public function setParams($params)
    {
        $this->routePrams->params = $params;
        return $this;
    }

    /**
     * 获取是否支持路由Cache
     *
     * @return bool
     */
    public function getEnableCache()
    {
        return $this->enableCache;
    }

    /**
     * 缓存路由
     *
     * @param string $path URL Path
     * @param array $callable 路由解析结果
     * @return $this
     */
    public function setRouteCache($path, $callable)
    {
        $this->routeCache[$path] = $callable;
        return $this;
    }

    /**
     * 获取已缓存的路由信息
     *
     * @param string $path URL Path
     * @return mixed|null
     */
    public function getRouteCache($path)
    {
        return $this->routeCache[$path] ?? null;
    }
}
