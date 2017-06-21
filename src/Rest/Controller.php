<?php

/**
 * Rest Controller
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Rest;

use PG\Exception\Errno;
use PG\MSF\Base\Output;

/**
 * Class Controller
 * @package PG\MSF\Controllers
 */
class Controller extends \PG\MSF\Controllers\Controller
{
    /**
     * @var string
     */
    public $verb = 'GET';
    /**
     * @var array the HTTP verbs that are supported by the collection URL
     */
    public $collectionOptions = ['GET', 'POST', 'HEAD', 'OPTIONS'];
    /**
     * @var array the HTTP verbs that are supported by the resource URL
     */
    public $resourceOptions = ['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /**
     * @param string $controllerName
     * @param string $methodName
     */
    public function initialization($controllerName, $methodName)
    {
        parent::initialization($controllerName, $methodName);
        $this->verb = $this->getContext()->getInput()->getRequestMethod();
    }

    /**
     * Returns whether this is a GET request.
     * @return bool whether this is a GET request.
     */
    public function getIsGet()
    {
        return $this->verb === 'GET';
    }

    /**
     * Returns whether this is an OPTIONS request.
     * @return bool whether this is a OPTIONS request.
     */
    public function getIsOptions()
    {
        return $this->verb === 'OPTIONS';
    }

    /**
     * Returns whether this is a HEAD request.
     * @return bool whether this is a HEAD request.
     */
    public function getIsHead()
    {
        return $this->verb === 'HEAD';
    }

    /**
     * Returns whether this is a POST request.
     * @return bool whether this is a POST request.
     */
    public function getIsPost()
    {
        return $this->verb === 'POST';
    }

    /**
     * Returns whether this is a DELETE request.
     * @return bool whether this is a DELETE request.
     */
    public function getIsDelete()
    {
        return $this->verb === 'DELETE';
    }

    /**
     * Returns whether this is a PUT request.
     * @return bool whether this is a PUT request.
     */
    public function getIsPut()
    {
        return $this->verb === 'PUT';
    }

    /**
     * Returns whether this is a PATCH request.
     * @return bool whether this is a PATCH request.
     */
    public function getIsPatch()
    {
        return $this->verb === 'PATCH';
    }

    /**
     * output Json
     * @param null $data
     * @param string $message
     * @param int $status
     * @param null $callback
     */
    public function outputJson($data = null, $message = '', $status = 200, $callback = null)
    {
        /* @var $output Output */
        $output = $this->getContext()->getOutput();
        // set status in header
        if (!isset(Output::$codes[$status])) {
            throw new \Exception('Http code invalid', Errno::FATAL);
        }
        $output->setStatusHeader($status);
        // 错误信息返回格式可参考：[https://developer.github.com/v3/]
        if ($status != 200 && $message !== '') {
            $data = [
                'message' => $message
            ];
        }
        $result = json_encode($data);
        if (!empty($output->response)) {
            $output->setContentType('application/json; charset=UTF-8');
            $output->end($result);
        }
    }

    /**
     * 对返回options的封装
     */
    public function outputOptions(array $options)
    {
        /* @var $output Output */
        $output = $this->getContext()->getOutput();
        if ($this->verb !== 'OPTIONS') {
            $output->setStatusHeader(405);
        }
        $output->setHeader('Allow', implode(', ', $options));
        if (!empty($output->response)) {
            $output->setContentType('application/json; charset=UTF-8');
            $output->end();
        }
    }
}
