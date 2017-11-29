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
    protected $routeParams;

    /**
     * @var string 控制器完全命名空间类名
     */
    public $controllerClassName;

    /**
     * @var string 默认控制器
     */
    public $defaultController = '';

    /**
     * @var string 默认控制器方法
     */
    public $defaultMethod = '';

    /**
     * @var string 网站根目录
     */
    public $domainRoot = '';

    /**
     * @var string 方法前缀
     */
    public $methodPrefix;

    /**
     * NormalRoute constructor.
     */
    public function __construct()
    {
        $this->routeParams       = new \stdClass();
        $this->defaultController = getInstance()->config->get("http.default_controller", '');
        $this->defaultMethod     = getInstance()->config->get('http.default_method', 'Index');
        $this->domainRoot        = getInstance()->config->get('http.domain', []);
        $this->methodPrefix      = getInstance()->config->get('http.method_prefix', 'action');
    }

    /**
     * 解析HTTP请求的基础信息
     *
     * @param \swoole_http_request $request
     * @return $this
     */
    public function parseRequestBase($request)
    {
        $this->routeParams->file  = '';
        $host = $request->header['host'] ?? '';
        if ($host) {
            $host = explode(':', $host)[0] ?? '';
        }
        $this->routeParams->host = $host;
        $this->routeParams->path = $this->defaultController ? $request->server['path_info'] : rtrim($request->server['path_info'], '/');
        $this->routeParams->verb = $this->parseVerb($request);
        $this->setParams($request->get ?? []);

        return $this;
    }

    /**
     * HTTP请求解析
     *
     * @param \swoole_http_request $request 请求对象
     */
    public function handleHttpRequest($request)
    {
        $this->parseRequestBase($request);

        if (isset($request->header['x-rpc']) && $request->header['x-rpc'] == 1) {
            $this->routeParams->isRpc          = true;
            $this->routeParams->params         = $request->post ?? $request->get ?? [];
            $this->routeParams->controllerName = 'Rpc';
            $this->routeParams->methodName     = 'Index';
            $this->controllerClassName         = '\PG\MSF\Controllers\Rpc';
            $this->routeParams->path           = '/Rpc/Index';
        } else {
            if ($this->routeParams->path) {
                $this->parsePath($this->routeParams->path);
            }
        }
    }

    /**
     * 计算Controller Class Name
     *
     * @param bool $loadDefault 是否加载默认的控制器
     * @return bool
     */
    public function findControllerClassName($loadDefault = false)
    {
        $this->controllerClassName = '';
        do {
            $className = "\\App\\Controllers\\" . $this->routeParams->controllerName;
            if (class_exists($className)) {
                $this->controllerClassName = $className;
                break;
            }

            $className = "\\PG\\MSF\\Controllers\\" . $this->routeParams->controllerName;
            if (class_exists($className)) {
                $this->controllerClassName = $className;
                break;
            }

            $className = "\\App\\Console\\" . $this->routeParams->controllerName;
            if (class_exists($className)) {
                $this->controllerClassName = $className;
                break;
            }

            if ($loadDefault) {
                if ($this->defaultController) {
                    $className = "\\App\\Controllers\\" . $this->defaultController;
                    if (class_exists($className)) {
                        $this->controllerClassName = $className;
                        break;
                    }
                }
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
            $this->routeParams->controllerName = $this->routeCache[$path][0];
            $this->routeParams->methodName     = $this->routeCache[$path][1];
            $this->controllerClassName         = $this->routeCache[$path][2];
        } else {
            if (stristr($path, '.')) {
                $this->routeParams->file = ($this->domainRoot[$this->getHost()]['root'] ?? ROOT_PATH . '/www') . $path;
                return true;
            }

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
                $methodName = $this->defaultMethod;
            }
            $this->routeParams->controllerName = ltrim(implode("\\", $route), "\\") ?? null;
            $this->routeParams->methodName     = $methodName;
            $this->controllerClassName         = '';

            if ($this->findControllerClassName()) {
                return true;
            }

            $controllerName = empty($this->routeParams->controllerName)
                ? $this->getMethodName()
                : $this->routeParams->controllerName . "\\" . $this->getMethodName();
            $this->setControllerName($controllerName);
            $this->setMethodName($this->defaultMethod);

            if ($this->findControllerClassName(true)) {
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
        return $this->routeParams->controllerName ?? '';
    }

    /**
     * 获取请求对应的控制器完全命名空间类名
     *
     * @return string
     */
    public function getControllerClassName()
    {
        return $this->controllerClassName ?? '';
    }

    /**
     * 获取方法名称
     *
     * @return string
     */
    public function getMethodName()
    {
        return $this->routeParams->methodName ?? '';
    }

    /**
     * 获取请求的PATH
     *
     * @return string
     */
    public function getPath()
    {
        return $this->routeParams->path ?? '';
    }

    /**
     * 获取静态文件路径
     *
     * @return string
     */
    public function getFile()
    {
        return $this->routeParams->file ?? '';
    }

    /**
     * 设置静态文件路径
     *
     * @param string $file 静态文件路径
     * @return $this
     */
    public function setFile($file)
    {
        $this->routeParams->file = $file;
        return $this;
    }

    /**
     * 获取请求报头的Host
     *
     * @return string
     */
    public function getHost()
    {
        return $this->routeParams->host ?? '';
    }


    /**
     * 判断请求是否为RPC请求
     *
     * @return bool
     */
    public function getIsRpc()
    {
        return $this->routeParams->isRpc ?? false;
    }

    /**
     * 获取请求的方法
     *
     * @return string|null
     */
    public function getVerb()
    {
        return $this->routeParams->verb ?? null;
    }

    /**
     * 获取请求的参数
     *
     * @return array
     */
    public function getParams()
    {
        return $this->routeParams->params ?? [];
    }

    /**
     * 设置请求的控制器标识
     *
     * @param string $name 控制器标识
     * @return $this
     */
    public function setControllerName($name)
    {
        $this->routeParams->controllerName = $name;
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
        $this->routeParams->methodName = $name;
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
        $this->routeParams->params = $params;
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
