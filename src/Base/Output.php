<?php
/**
 * Output
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\MSF\Macro;
use PG\MSF\Controllers\Controller;

/**
 * Class Output
 * @package PG\MSF\Base
 */
class Output extends Core
{
    /**
     * @link https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     * @var array HTTP状态码
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
     * @var \swoole_http_response HTTP响应对象
     */
    public $response;

    /**
     * @var \swoole_http_request HTTP请求对象
     */
    public $request;

    /**
     * @var bool 是否响应
     */
    public $__isEnd = false;

    /**
     * @var Controller 当前处理请求的控制器
     */
    protected $controller;

    /**
     * 构造方法
     *
     * @param Controller $controller 控制器实例
     */
    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * 属性用于序列化
     *
     * @return array
     */
    public function __sleep()
    {
        return [];
    }

    /**
     * 设置HTTP请求和HTTP响应对象
     *
     * @param \swoole_http_request $request HTTP请求对象
     * @param \swoole_http_response|null $response HTTP请求响应对象
     * @return $this
     */
    public function set(&$request, &$response = null)
    {
        $this->request  = $request;
        $this->response = $response;
        return $this;
    }

    /**
     * 设置响应的Content-Type报头
     *
     * @param string $mime_type Content-Type的值，如application/json;charset=utf-8
     * @return $this
     */
    public function setContentType($mime_type)
    {
        $this->setHeader('Content-Type', $mime_type);
        return $this;
    }

    /**
     * 设置响应的其他HTTP报头
     *
     * @param string $key HTTP报头Key
     * @param string $value HTTP报头Value
     * @return $this
     */
    public function setHeader($key, $value)
    {
        $this->response->header($key, $value);
        return $this;
    }

    /**
     * 设置HTTP响应的cookie信息，与PHP的setcookie()参数一致
     *
     * @param string $key Cookie名称
     * @param string $value Cookie值
     * @param int $expire 有效时间
     * @param string $path 有效路径
     * @param string $domain 有效域名
     * @param bool $secure Cookie是否仅仅通过安全的HTTPS连接传给客户端
     * @param bool $httponly 设置成TRUE，Cookie仅可通过HTTP协议访问
     * @return $this
     */
    public function setCookie(
        $key,
        $value = '',
        $expire = 0,
        $path = '/',
        $domain = '',
        $secure = false,
        $httponly = false
    ) {
        $this->response->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
        return $this;
    }

    /**
     * 响应原始数据
     *
     * @param mixed|null $data 响应数据
     * @param int $httpCode 响应HTTP状态码
     */
    public function output($data = null, $httpCode = 200)
    {
        if (!empty($this->response)) {
            $this->end($data, $httpCode);
        }
    }

    /**
     * 响应json格式数据
     *
     * @param mixed|null $data 响应数据
     * @param int $httpCode 响应HTTP状态码
     */
    public function outputJson($data = null, $httpCode = 200)
    {
        $this->setContentType('application/json; charset=UTF-8');
        $data = json_encode($data);

        if (!empty($this->response)) {
            $this->end($data, $httpCode);
        }
    }

    /**
     * 通过模板引擎响应输出HTML
     *
     * @param array $data 待渲染KV数据
     * @param string|null $view 文件名
     * @throws \Exception
     * @throws \Throwable
     * @throws Exception
     */
    public function outputView(array $data, $view = null)
    {
        if ($this->controller->requestType !== Macro::HTTP_REQUEST) {
            throw new Exception('$this->outputView not support '. $this->controller->requestType);
        }

        $this->setContentType('text/html; charset=UTF-8');
        if (empty($view)) {
            $view = str_replace('\\', '/', $this->getContext()->getControllerName()) . '/' .
                str_replace($this->getConfig()->get('http.method_prefix', 'action'), '', $this->getContext()->getActionName());
        }

        $responseBody = null;
        $found = false;
        $engine = getInstance()->templateEngine;
        $viewResolvePaths = getInstance()->viewResolvePaths;

        foreach ($viewResolvePaths as $basePath) {
            $template = $engine->setDirectory($basePath)->make($view);
            if (!$template->exists()) {
                continue;
            }
            $responseBody = $template->render($data);
            $found = true;
            break;
        }
        if (!$found) {
            throw new Exception(sprintf('A template named %s was not found in any folder, please check again', $view));
        }
        $this->end($responseBody);
    }

    /**
     * 结束HTTP请求，发送响应体
     *
     * @param string $output 发送的数据
     * @param int $httpCode 响应的HTTP状态码
     * @param bool $gzip 是否启用gzip压缩
     */
    public function end($output = '', $httpCode = 200, $gzip = true)
    {
        $this->setHeader('X-Ngx-LogId', $this->getContext()->getLogId());
        $this->getContext()->getLog()->pushLog('http-code', $httpCode);
        $acceptEncoding = strtolower($this->request->header['accept-encoding'] ?? '');
        if ($gzip && strpos($acceptEncoding, 'gzip') !== false) {
            $this->response->gzip(1);
        }

        if (!is_string($output)) {
            $this->setHeader('Content-Type', 'application/json');
            $output = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $this->getContext()->getLog()->pushLog('content-length', strlen($output));
        $this->setStatusHeader($httpCode);
        $this->response->end($output);
        $this->__isEnd = true;
    }

    /**
     * 设置HTTP响应状态码
     *
     * @param int $code HTTP状态码
     * @return $this
     */
    private function setStatusHeader($code = 200)
    {
        if (empty(self::$codes[$code])) {
            $code = 200;
        }

        if (!empty($this->response)) {
            $this->response->status($code);
        }

        return $this;
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
