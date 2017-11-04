<?php
/**
 * Restful Api控制器基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Rest;

use PG\MSF\Base\Output;

/**
 * Class Controller
 * @package PG\MSF\Rest
 */
class Controller extends \PG\MSF\Controllers\Controller
{
    /**
     * @var string HTTP请求方法
     */
    public $verb = 'GET';
    /**
     * @var array 操作资源集合所支持的HTTP请求方法
     */
    public $collectionOptions = ['GET', 'POST', 'HEAD', 'OPTIONS'];
    /**
     * @var array 操作资源所支持的HTTP请求方法
     */
    public $resourceOptions = ['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /**
     * 构造方法
     *
     * @param string $controllerName controller标识
     * @param string $methodName method名称
     */
    public function __construct($controllerName, $methodName)
    {
        parent::__construct($controllerName, $methodName);
        $this->verb = $this->getContext()->getInput()->getRequestMethod();
    }

    /**
     * 返回当前请求是否为GET
     *
     * @return bool
     */
    public function getIsGet()
    {
        return $this->verb === 'GET';
    }

    /**
     * 返回当前请求是否为OPTIONS
     *
     * @return bool
     */
    public function getIsOptions()
    {
        return $this->verb === 'OPTIONS';
    }

    /**
     * 返回当前请求是否为HEAD
     *
     * @return bool
     */
    public function getIsHead()
    {
        return $this->verb === 'HEAD';
    }

    /**
     * 返回当前请求是否为POST
     *
     * @return bool
     */
    public function getIsPost()
    {
        return $this->verb === 'POST';
    }

    /**
     * 返回当前请求是否为DELETE
     *
     * @return bool
     */
    public function getIsDelete()
    {
        return $this->verb === 'DELETE';
    }

    /**
     * 返回当前请求是否为PUT
     *
     * @return bool
     */
    public function getIsPut()
    {
        return $this->verb === 'PUT';
    }

    /**
     * 返回当前请求是否为PATCH
     *
     * @return bool
     */
    public function getIsPatch()
    {
        return $this->verb === 'PATCH';
    }

    /**
     * 响应json格式数据
     *
     * @param mixed|null $data 响应数据
     * @param string $message 响应提示
     * @param int $status 响应HTTP状态码
     * @throws \Exception
     */
    public function outputJson($data = null, $message = '', $status = 200)
    {
        // 错误信息返回格式可参考：[https://developer.github.com/v3/]
        if ($status != 200 && $message !== '') {
            $data = [
                'message' => $message
            ];
        }
        parent::outputJson($data, $status);
    }

    /**
     * 响应options请求
     *
     * @param array $options OPTIONS
     */
    public function outputOptions(array $options)
    {
        /* @var $output Output */
        $output = $this->getContext()->getOutput();
        $status = 200;
        if ($this->verb !== 'OPTIONS') {
            $status = 405;
        }
        $output->setHeader('Allow', implode(', ', $options));
        if (!empty($output->response)) {
            $output->setContentType('application/json; charset=UTF-8');
            $output->end('', $status);
        }
    }

    /**
     * 异常的回调
     *
     * @param \Throwable $e 异常
     * @throws \Throwable
     */
    public function onExceptionHandle(\Throwable $e)
    {
        try {
            if ($e->getPrevious()) {
                $ce = $e->getPrevious();
                $errMsg = dump($ce, false, true);
            } else {
                $errMsg = dump($e, false, true);
                $ce = $e;
            }

            $this->getContext()->getLog()->error($errMsg);
            $this->outputJson(parent::$stdClass, 'Internal Server Error', 500);
        } catch (\Throwable $ne) {
            getInstance()->log->error('previous exception ' . dump($ce, false, true));
            getInstance()->log->error('handle exception ' . dump($ne, false, true));
        }
    }
}
