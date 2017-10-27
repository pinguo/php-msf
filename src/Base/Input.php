<?php
/**
 * 请求的输入对象（用于代替传统$_GET/$_POST/$_SERVER）
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

/**
 * Class Input
 * @package PG\MSF\Base
 */
class Input extends Core
{
    /**
     * @var \swoole_http_request|\PG\MSF\Console\Request 请求参数对象
     */
    public $request;

    /**
     * @var \swoole_http_request 用于自动序列化（解决序列化资源被析构的问题）
     */
    public $__serializeRequest;

    /**
     * 属性用于序列化
     *
     * @return array
     */
    public function __sleep()
    {
        $this->__serializeRequest          = new \stdClass();
        $this->__serializeRequest->get     = $this->request->get  ?? [];
        $this->__serializeRequest->post    = $this->request->post ?? [];
        $this->__serializeRequest->files   = $this->request->files ?? [];
        $this->__serializeRequest->cookie  = $this->request->cookie ?? [];
        $this->__serializeRequest->header  = $this->request->header ?? [];
        $this->__serializeRequest->server  = $this->request->server ?? [];

        return ['__serializeRequest'];
    }

    /**
     * 反序列化后的初始化操作
     */
    public function __wakeup()
    {
        $this->request = $this->__serializeRequest;
    }

    /**
     * 设置请求参数对象
     *
     * @param \swoole_http_request|\PG\MSF\Console\Request $request 请求参数对象
     * @return $this
     */
    public function set($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * 重置请求参数对象
     *
     * @return $this
     */
    public function reset()
    {
        unset($this->request);
        return $this;
    }

    /**
     * 获取POST/GET参数，优先读取POST
     *
     * @param string $index 请求参数名
     * @return string
     */
    public function postGet($index)
    {
        return $this->request->post[$index] ?? $this->get($index);
    }

    /**
     * 获取POST参数
     *
     * @param string $index POST参数名
     * @return string
     */
    public function post($index)
    {
        return $this->request->post[$index] ?? '';
    }

    /**
     * 获取GET参数
     *
     * @param string $index GET参数名
     * @return string
     */
    public function get($index)
    {
        return $this->request->get[$index] ?? '';
    }

    /**
     * 获取POST/GET参数，优先读取GET
     *
     * @param string $index 请求参数名
     * @return string
     */
    public function getPost($index)
    {
        return $this->request->get[$index] ?? $this->post($index);
    }

    /**
     * 获取所有的POST参数和Get参数
     *
     * @return array
     */
    public function getAllPostGet()
    {
        if (isset($this->request->post, $this->request->get)) {
            return array_merge($this->request->get, $this->request->post);
        }
        return $this->request->post ?? $this->request->get ?? [];
    }

    /**
     * 获取所有的POST参数
     *
     * @return array
     */
    public function getAllPost()
    {
        return $this->request->post ?? [];
    }

    /**
     * 获取所有的GET参数
     *
     * @return array
     */
    public function getAllGet()
    {
        return $this->request->get ?? [];
    }


    /**
     * 获取请求的所有报头
     *
     * @return array
     */
    public function getAllHeader()
    {
        return $this->request->header ?? [];
    }

    /**
     * 获取处理请求的Server信息
     *
     * @return array
     */
    public function getAllServer()
    {
        return $this->request->server ?? [];
    }

    /**
     * 获取原始的POST包体
     *
     * @return string
     */
    public function getRawContent()
    {
        return $this->request->rawContent();
    }

    /**
     * 设置POST请求参数
     *
     * @param string $key 设置参数的key
     * @param mixed $value 设置参数的value
     * @return $this
     */
    public function setPost($key, $value)
    {
        $this->request->post[$key] = $value;
        return $this;
    }

    /**
     * 设置GET请求参数
     *
     * @param string $key 设置参数的key
     * @param mixed $value 设置参数的value
     * @return $this
     */
    public function setGet($key, $value)
    {
        $this->request->get[$key] = $value;
        return $this;
    }

    /**
     * 设置所有POST请求参数
     *
     * @param array $post 所有的待设置的POST请求参数
     * @return $this
     */
    public function setAllPost(array $post)
    {
        $this->request->post = $post;
        return $this;
    }

    /**
     * 设置所有GET请求参数
     *
     * @param array $get 所有的待设置的GET请求参数
     * @return $this
     */
    public function setAllGet(array $get)
    {
        $this->request->get = $get;
        return $this;
    }

    /**
     * 获取Cookie参数
     *
     * @param string $index Cookie参数名
     * @return string
     */
    public function getCookie($index)
    {
        return $this->request->cookie[$index] ?? '';
    }

    /**
     * 获取上传文件信息
     *
     * @param string $index File文件名
     * @return array
     */
    public function getFile($index)
    {
        return $this->request->files[$index] ?? '';
    }

    /**
     * 获取Server相关的数据
     *
     * @param string $index 获取的Server参数名
     * @return array|bool|string
     */
    public function getServer($index)
    {
        return $this->request->server[$index] ?? '';
    }

    /**
     * 获取请求的方法
     *
     * @return string
     */
    public function getRequestMethod()
    {
        if (isset($this->request->server['http_x_http_method_override'])) {
            return strtoupper($this->request->server['http_x_http_method_override']);
        }
        if (isset($this->request->server['request_method'])) {
            return strtoupper($this->request->server['request_method']);
        }

        return 'GET';
    }

    /**
     * 获取请求的URI
     *
     * @return string
     */
    public function getRequestUri()
    {
        return $this->request->server['request_uri'];
    }

    /**
     * 获取请求的PATH
     *
     * @return string
     */
    public function getPathInfo()
    {
        return $this->request->server['path_info'];
    }

    /**
     * 获取请求的用户ID
     *
     * @return string
     */
    public function getRemoteAddr()
    {
        return getInstance()::getRemoteAddr($this->request);
    }

    /**
     * 获取请求报头参数
     *
     * @param string $index 请求报头参数名
     * @return string
     */
    public function getHeader($index)
    {
        return $this->request->header[$index] ?? '';
    }

    /**
     * 销毁,解除引用
     */
    public function destroy()
    {
        parent::destroy();
    }
}
