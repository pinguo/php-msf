<?php
/**
 * Output
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use Exception;
use PG\MSF\Marco;
use PG\MSF\Controllers\Controller;

class Output extends Core
{
    /**
     * [https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html]
     * @var array
     */
    public static $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        // 用于一般性的成功返回
        201 => 'Created',
        // 资源被创建
        202 => 'Accepted',
        // 用于Controller控制类资源异步处理的返回，仅表示请求已经收到。对于耗时比较久的处理，一般用异步处理来完成
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        // 此状态可能会出现在PUT、POST、DELETE的请求中，一般表示资源存在，但消息体中不会返回任何资源相关的状态或信息
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        210 => 'Content Different',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        // 资源的URI被转移，需要使用新的URI访问
        302 => 'Found',
        // 不推荐使用，此代码在HTTP1.1协议中被303/307替代。我们目前对302的使用和最初HTTP1.0定义的语意是有出入的，应该只有在GET/HEAD方法下，客户端才能根据Location执行自动跳转，而我们目前的客户端基本上是不会判断原请求方法的，无条件的执行临时重定向
        303 => 'See Other',
        // 返回一个资源地址URI的引用，但不强制要求客户端获取该地址的状态(访问该地址)
        304 => 'Not Modified',
        // 有一些类似于204状态，服务器端的资源与客户端最近访问的资源版本一致，并无修改，不返回资源消息体。可以用来降低服务端的压力
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        // 目前URI不能提供当前请求的服务，临时性重定向到另外一个URI。在HTTP1.1中307是用来替代早期HTTP1.0中使用不当的302
        308 => 'Permanent Redirect',
        310 => 'Too many Redirect',
        400 => 'Bad Request',
        // 用于客户端一般性错误返回, 在其它4xx错误以外的错误，也可以使用400，具体错误信息可以放在body中
        401 => 'Unauthorized',
        // 在访问一个需要验证的资源时，验证错误
        402 => 'Payment Required',
        403 => 'Forbidden',
        //  一般用于非验证性资源访问被禁止，例如对于某些客户端只开放部分API的访问权限，而另外一些API可能无法访问时，可以给予403状态
        404 => 'Not Found',
        // 找不到URI对应的资源
        405 => 'Action Not Allowed',
        // HTTP的方法不支持，例如某些只读资源，可能不支持POST/DELETE。但405的响应header中必须声明该URI所支持的方法
        406 => 'Not Acceptable',
        // 客户端所请求的资源数据格式类型不被支持，例如客户端请求数据格式为application/xml，但服务器端只支持application/json
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        // 资源状态冲突，例如客户端尝试删除一个非空的Store资源
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        // 用于有条件的操作不被满足时
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Method failure',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        // 服务器端的接口错误，此错误于客户端无关
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        // 网关错误
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        507 => 'Insufficient storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

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
     * 构造方法
     *
     * @param Controller $controller
     */
    public function __construct($controller)
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
    public function set(&$request, &$response = null)
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
     * 响应通过模板输出的HTML
     *
     * @param array $data
     * @param string|null $view
     * @throws \Exception
     * @throws \Throwable
     * @throws Exception
     * @return void
     */
    public function outputView(array $data, $view = null)
    {
        if ($this->controller->requestType !== Marco::HTTP_REQUEST) {
            throw new Exception('$this->outputView not support '. $this->controller->requestType);
        }

        $this->setContentType('text/html; charset=UTF-8');
        if (empty($view)) {
            $view = str_replace('\\', '/', $this->getContext()->getControllerName()) . '/' .
                str_replace($this->getConfig()->get('http.method_prefix', ''), '', $this->getContext()->getActionName());
        }

        try {
            $viewFile = ROOT_PATH . '/app/Views/' . $view;
            $template = getInstance()->templateEngine->make($viewFile);
            $response = $template->render($data);
        } catch (\Throwable $e) {
            $template = null;
            $viewFile = getInstance()->MSFSrcDir . '/Views/' . $view;
            try {
                $template = getInstance()->templateEngine->make($viewFile);
                $response = $template->render($data);
            } catch (\Throwable $e) {
                throw new Exception('app view and server view both not exist, please check again', 500);
            }
        }

        $template = null;
        $this->end($response);
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
     * 销毁,解除引用
     */
    public function destroy()
    {
        parent::destroy();
        $this->controller = null;
    }
}
