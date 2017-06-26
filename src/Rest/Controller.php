<?php

/**
 * Rest Controller
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Rest;

use PG\Exception\ParameterValidationExpandException;
use PG\Exception\PrivilegeException;
use PG\MSF\Base\Output;
use PG\MSF\Coroutine\CException;

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
            throw new \Exception('Http code invalid', 500);
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

    /**
     * 异常的回调
     * @param \Throwable $e
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

            if ($ce instanceof ParameterValidationExpandException) {
                $this->getContext()->getLog()->warning($errMsg . ' with code 401');
                $this->outputJson(parent::$stdClass, $ce->getMessage(), 401);
            } elseif ($ce instanceof PrivilegeException) {
                $this->getContext()->getLog()->warning($errMsg . ' with code 403');
                $this->outputJson(parent::$stdClass, $ce->getMessage(), 403);
            } elseif ($ce instanceof \MongoException) {
                $this->getContext()->getLog()->error($errMsg . ' with code 500');
                $this->outputJson(parent::$stdClass, Output::$codes[500], 500);
            } elseif ($ce instanceof CException) {
                $this->getContext()->getLog()->error($errMsg . ' with code 500');
                $this->outputJson(parent::$stdClass, $ce->getMessage(), 500);
            } else {
                $this->getContext()->getLog()->error($errMsg . ' with code ' . $ce->getCode());
                // set status in header
                if (isset(Output::$codes[$ce->getCode()])) {
                    $this->outputJson(parent::$stdClass, $ce->getMessage(), $ce->getCode());
                } else {
                    $this->outputJson(parent::$stdClass, $ce->getMessage(), 500);
                }
            }
        } catch (\Throwable $ne) {
            echo 'Call Controller::onExceptionHandle Error', "\n";
            echo 'Last Exception: ', dump($ce), "\n";
            echo 'Handle Exception: ', dump($ne), "\n";
        }
    }
}
