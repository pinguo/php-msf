<?php
/**
 * IRoute接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Route;

/**
 * Interface IRoute
 * @package PG\MSF\Route
 */
interface IRoute
{
    /**
     * HTTP请求解析
     *
     * @param \swoole_http_client $request 请求对象
     */
    public function handleHttpRequest($request);

    /**
     * 获取控制器名称
     *
     * @return string
     */
    public function getControllerName();

    /**
     * 获取请求对应的控制器完全命名空间类名
     *
     * @return string
     */
    public function getControllerClassName();

    /**
     * 计算Controller Class Name
     *
     * @return bool
     */
    public function findControllerClassName();

    /**
     * 获取方法名称
     *
     * @return string
     */
    public function getMethodName();

    /**
     * 获取请求的参数
     *
     * @return array
     */
    public function getParams();

    /**
     * 获取请求的PATH
     *
     * @return string
     */
    public function getPath();

    /**
     * 判断请求是否为RPC请求
     *
     * @return bool
     */
    public function getIsRpc();

    /**
     * 获取请求的方法
     *
     * @return string|null
     */
    public function getVerb();

    /**
     * 设置请求的控制器标识
     *
     * @param string $name 控制器标识
     * @return $this
     */
    public function setControllerName($name);

    /**
     * 设置请求控制器的方法标识
     *
     * @param string $name 控制器的方法标识
     * @return $this
     */
    public function setMethodName($name);

    /**
     * 设置请求的参数
     *
     * @param array $params 请求的参数
     * @return $this
     */
    public function setParams($params);

    /**
     * 获取是否支持路由Cache
     *
     * @return bool
     */
    public function getEnableCache();

    /**
     * 缓存路由
     *
     * @param string $path URL Path
     * @param array $callable 路由解析结果
     * @return $this
     */
    public function setRouteCache($path, $callable);

    /**
     * 获取已缓存的路由信息
     *
     * @param string $path URL Path
     * @return mixed|null
     */
    public function getRouteCache($path);


    /**
     * 获取请求报头的Host.
     *
     * @return string
     */
    public function getHost();

    /**
     * 获取文件.
     *
     * @return mixed
     */
    public function getFile();
}
