<?php
/**
 * Input
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

class Input extends Core
{
    /**
     * @var \swoole_http_request|\stdClass
     */
    public $request;

    public function __sleep()
    {
        return ['request'];
    }

    /**
     * @param $request
     */
    public function set($request)
    {
        $this->request = $request;
    }

    /**
     * 重置
     */
    public function reset()
    {
        unset($this->request);
    }

    /**
     * postGet
     * @param $index
     * @param $xssClean
     * @return string
     */
    public function postGet($index, $xssClean = true)
    {
        return isset($this->request->post[$index])
            ? $this->post($index, $xssClean)
            : $this->get($index, $xssClean);
    }

    /**
     * post
     * @param $index
     * @param $xssClean
     * @return string
     */
    public function post($index, $xssClean = true)
    {
        if ($xssClean) {
            return XssClean::getXssClean()->xss_clean($this->request->post[$index]??'');
        } else {
            return $this->request->post[$index]??'';
        }
    }

    /**
     * get
     * @param $index
     * @param $xssClean
     * @return string
     */
    public function get($index, $xssClean = true)
    {
        if ($xssClean) {
            return XssClean::getXssClean()->xss_clean($this->request->get[$index]??'');
        } else {
            return $this->request->get[$index]??'';
        }
    }

    /**
     * getPost
     * @param $index
     * @param $xssClean
     * @return string
     */
    public function getPost($index, $xssClean = true)
    {
        return isset($this->request->get[$index])
            ? $this->get($index, $xssClean)
            : $this->post($index, $xssClean);
    }

    /**
     * 获取所有的post和get
     */
    public function getAllPostGet()
    {
        return $this->request->post??$this->request->get??[];
    }

    /**
     * 获取所有的post
     */
    public function getAllPost()
    {
        return $this->request->post;
    }

    /**
     * 获取所有的get
     */
    public function getAllGet()
    {
        return $this->request->get;
    }


    /**
     * getAllHeader
     * @return array
     */
    public function getAllHeader()
    {
        return $this->request->header;
    }

    /**
     * getAllServer
     * @return array
     */
    public function getAllServer()
    {
        return $this->request->server;
    }

    /**
     * 获取原始的POST包体
     * @return mixed
     */
    public function getRawContent()
    {
        return $this->request->rawContent();
    }

    /**
     * 设置POST请求参数
     *
     * @param string $key
     * @param mixed $value
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
     * @param string $key
     * @param mixed $value
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
     * @param $post
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
     * @param $get
     * @return $this
     */
    public function setAllGet(array $get)
    {
        $this->request->get = $get;
        return $this;
    }

    /**
     * cookie
     * @param $index
     * @param $xssClean
     * @return string
     */
    public function cookie($index, $xssClean = true)
    {
        if ($xssClean) {
            return XssClean::getXssClean()->xss_clean($this->request->cookie[$index]??'');
        } else {
            return $this->request->cookie[$index]??'';
        }
    }

    /**
     * 获取Server相关的数据
     * @param $index
     * @param bool $xssClean
     * @return array|bool|string
     */
    public function server($index, $xssClean = true)
    {
        if ($xssClean) {
            return XssClean::getXssClean()->xss_clean($this->request->server[$index]??'');
        } else {
            return $this->request->server[$index]??'';
        }
    }

    /**
     * @return mixed
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
     * @return mixed
     */
    public function getRequestUri()
    {
        return $this->request->server['request_uri'];
    }

    /**
     * @return mixed
     */
    public function getPathInfo()
    {
        return $this->request->server['path_info'];
    }

    /**
     * @return string
     */
    public function getRemoteAddr()
    {
        if (($ip = $this->getRequestHeader('x-forwarded-for')) || ($ip = $this->getRequestHeader('http_x_forwarded_for'))
            || ($ip = $this->getRequestHeader('http_forwarded')) || ($ip = $this->getRequestHeader('http_forwarded_for'))
            || ($ip = $this->getRequestHeader('http_forwarded'))
        ) {
            $ip = explode(',', $ip);
            $ip = trim($ip[0]);
        } elseif ($ip = $this->getRequestHeader('http_client_ip')) {
        } elseif ($ip = $this->getRequestHeader('x-real-ip')) {
        } elseif ($ip = $this->getRequestHeader('remote_addr')) {
        } elseif ($ip = $this->request->server['remote_addr']) {
            // todo
        }

        return $ip;
    }

    /**
     * getRequestHeader
     * @param $index
     * @param $xssClean
     * @return string
     */
    public function getRequestHeader($index, $xssClean = false)
    {
        if ($xssClean) {
            return XssClean::getXssClean()->xss_clean($this->request->header[$index]??'');
        } else {
            return $this->request->header[$index]??'';
        }
    }

    /**
     * 销毁,解除引用
     */
    public function destroy()
    {
        parent::destroy();
    }
}
