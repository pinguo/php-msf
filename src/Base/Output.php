<?php
/**
 * Output
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\MSF\Marco;
use PG\MSF\Controllers\Controller;

class Output extends Core
{
    /**
     * http response
     * @var \swoole_http_response
     */
    public $response;

    /**
     * http request
     * @var \swoole_http_request
     */
    public $request;

    /**
     * @var Controller
     */
    protected $controller;

    /**
     * 初始化Output
     *
     * @param Controller $controller
     */
    public function initialization($controller)
    {
        $this->controller = $controller;
    }

    public function __sleep()
    {
        return ['response', 'request'];
    }

    /**
     * 设置
     * @param $request
     * @param $response
     */
    public function set($request, $response)
    {
        $this->request  = $request;
        $this->response = $response;
    }

    /**
     * Set HTTP Status Header
     *
     * @param    int    the status code
     * @param    string
     * @return HttpOutPut
     */
    public function setStatusHeader($code = 200)
    {
        $this->response->status($code);
        return $this;
    }

    /**
     * 响应json格式数据
     *
     * @param Controller $controller
     * @param null $data
     * @param string $message
     * @param int $status
     * @param null $callback
     * @return array
     */
    public function outputJson($data = null, $message = '', $status = 200, $callback = null)
    {
        $this->getContext()->getLog()->pushLog('status', $status);

        $result = [
            'data'       => $data,
            'status'     => $status,
            'message'    => $message,
            'serverTime' => microtime(true),
        ];

        switch ($this->controller->requestType) {
            case Marco::HTTP_REQUEST:
                $callback = $this->getCallback($callback);
                if (!is_null($callback)) {
                    $output = $callback . '(' . json_encode($result) . ');';
                } else {
                    $output = json_encode($result);
                }

                if (!empty($this->response)) {
                    $this->setContentType('application/json; charset=UTF-8');
                    $this->end($output);
                }
                break;
            case Marco::TCP_REQUEST:
                $output = json_encode($result);
                $this->controller->send($output);
                break;
        }
    }

    /**
     * 获取jsonp的callback名称
     *
     * @param $callback
     * @return string
     */
    public function getCallback($callback)
    {
        $input = $this->getContext()->getInput();
        if (is_null($callback) && (!empty($input->postGet('callback')) ||
                !empty($input->postGet('cb')) ||
                !empty($input->postGet('jsonpCallback')))
        ) {
            $callback = !empty($input->postGet('callback'))
                ? $input->postGet('callback')
                : !empty($input->postGet('cb'))
                    ? $input->postGet('cb')
                    : $input->postGet('jsonpCallback');
        }

        return $callback;
    }

    /**
     * Set Content-Type Header
     *
     * @param string $mime_type Extension of the file we're outputting
     * @return $this
     */
    public function setContentType($mime_type)
    {
        $this->setHeader('Content-Type', $mime_type);
        return $this;
    }

    /**
     * set_header
     * @param $key
     * @param $value
     * @return $this
     */
    public function setHeader($key, $value)
    {
        $this->response->header($key, $value);
        return $this;
    }

    /**
     * 发送
     * @param string $output
     * @param bool $gzip
     * @param bool $destroy
     */
    public function end($output = '', $gzip = true, $destroy = true)
    {
        $acceptEncoding = strtolower($this->request->header['accept-encoding'] ?? '');
        if ($gzip && strpos($acceptEncoding, 'gzip') !== false) {
            $this->response->gzip(1);
        }

        if (!is_string($output)) {
            $this->setHeader('Content-Type', 'application/json');
            $output = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $this->response->end($output);
        if ($destroy) {
            $this->controller->destroy();
        }
    }

    /**
     * 设置HTTP响应的cookie信息。此方法参数与PHP的setcookie完全一致。
     * @param string $key
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     */
    public function setCookie(
        string $key,
        string $value = '',
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false
    ) {
        $this->response->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 输出文件
     * @param $root_file
     * @param $file_name
     * @param bool $destroy
     * @return mixed
     */
    public function endFile($root_file, $file_name, $destroy = true)
    {
        $result = httpEndFile($root_file . '/' . $file_name, $this->request, $this->response);
        if ($destroy) {
            $this->controller->destroy();
        }
        return $result;
    }

    /**
     * 销毁,解除引用
     */
    public function destroy()
    {
        $this->response = null;
        $this->request = null;
        $this->controller = null;
        parent::destroy();
    }
}
