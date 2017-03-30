<?php
/**
 * NormalRoute
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Route;

class NormalRoute implements IRoute
{
    private $client_data;

    public function __construct()
    {
        $this->client_data = new \stdClass();
    }

    /**
     * 设置反序列化后的数据 Object
     * @param $data
     * @return \stdClass
     */
    public function handleClientData($data)
    {
        $this->client_data = $data;
        $this->parsePath($data->path);
        return $this->client_data;
    }

    /**
     * 处理http request
     * @param $request
     */
    public function handleClientRequest($request)
    {
        $this->client_data->path = $request->server['path_info'];
        $this->parsePath($this->client_data->path);
    }

    /**
     * 解析path
     *
     * @param $path
     */
    public function parsePath($path)
    {
        $route = explode('/', $path);
        $route = array_map(function ($name) {
            $name = strtolower($name);
            $name = ucfirst($name);
            return $name;
        }, $route);
        $method_name = array_pop($route);
        $this->client_data->controller_name = ltrim(implode("\\", $route), "\\")??null;
        $this->client_data->method_name = $method_name;
    }

    /**
     * 获取控制器名称
     * @return string
     */
    public function getControllerName()
    {
        return $this->client_data->controller_name;
    }

    /**
     * 获取方法名称
     * @return string
     */
    public function getMethodName()
    {
        return $this->client_data->method_name;
    }

    public function getPath()
    {
        return $this->client_data->path;
    }

    public function getParams()
    {
        return $this->client_data->params??null;
    }

    public function setControllerName($name)
    {
        $this->client_data->controller_name = $name;
    }

    public function setMethodName($name)
    {
        $this->client_data->method_name = $name;
    }
}