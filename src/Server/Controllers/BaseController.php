<?php declare(strict_types = 1);
/**
 * @desc: 控制器基类
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/2/9
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

use PG\Exception\BusinessException;
use PG\Exception\Errno;
use PG\Exception\ParameterValidationExpandException;
use PG\Exception\PrivilegeException;
use PG\Log\PGLog;
use PG\MSF\Server\{
    CoreBase\Controller, Helpers\Context, Marco
};

class BaseController extends Controller
{
    /**
     * @var string
     */
    public $logId;

    /**
     * @var PGLog
     */
    public $PGLog;

    /**
     * @var float 请求开始处理的时间
     */
    public $requestStartTime = 0.0;

    public function initialization($controllerName, $methodName)
    {
        $this->requestStartTime = microtime(true);
        $this->PGLog = null;
        $this->PGLog = clone $this->logger;
        $this->PGLog->accessRecord['beginTime'] = $this->requestStartTime;
        $this->PGLog->accessRecord['uri'] = str_replace('\\', '/', '/' . $controllerName . '/' . $methodName);
        $this->PGLog->logId = $this->genLogId();
        defined('SYSTEM_NAME') && $this->PGLog->channel = SYSTEM_NAME;
        $this->PGLog->init();

        $context = $this->objectPool->get(Context::class);
        $context->logId = $this->logId;
        $context->PGLog = $this->PGLog;
        $context->input = $this->input;
        $context->output = $this->output;
        $context->controller = $this;
        $this->client->context = $context;
        $this->tcpClient->context = $context;
        $this->setContext($context);
    }

    /**
     * gen a logId
     * @return string
     */
    public function genLogId()
    {
        if ($this->requestType == Marco::HTTP_REQUEST) {
            $this->logId = $this->input->getRequestHeader('log_id') ?? '';
        } else {
            $this->logId = $this->clientData->logId ?? '';
        }

        if (!$this->logId) {
            $this->logId = strval(new \MongoId());
        }

        return $this->logId;
    }

    public function destroy()
    {
        $this->PGLog->appendNoticeLog();
        parent::destroy();
    }

    /**
     * 异常的回调
     *
     * @param \Throwable $e
     * @throws \PG\MSF\Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function onExceptionHandle(\Throwable $e)
    {
        $errMsg = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $errMsg .= ' Trace: ' . $e->getTraceAsString();
        if (!empty($e->getPrevious())) {
            $errMsg .= ' Previous trace: ' . $e->getPrevious()->getTraceAsString();
        }

        if ($e instanceof ParameterValidationExpandException) {
            $this->PGLog->warning($errMsg . ' with code ' . Errno::PARAMETER_VALIDATION_FAILED);
            $this->outputJson([], $e->getMessage(), Errno::PARAMETER_VALIDATION_FAILED);
        } elseif ($e instanceof PrivilegeException) {
            $this->PGLog->warning($errMsg . ' with code ' . Errno::PRIVILEGE_NOT_PASS);
            $this->outputJson([], $e->getMessage(), Errno::PRIVILEGE_NOT_PASS);
        } elseif ($e instanceof BusinessException) {
            $this->PGLog->warning($errMsg . ' with code ' . $e->getCode());
            $this->outputJson([], $e->getMessage(), Errno::PRIVILEGE_NOT_PASS);
        } elseif ($e instanceof \MongoException) {
            $this->PGLog->error($errMsg . ' with code ' . $e->getCode());
            $this->outputJson([], 'Network Error.', Errno::FATAL);
        } elseif ($e instanceof \Exception) {
            $this->PGLog->error($errMsg . ' with code ' . $e->getCode());
            $this->outputJson([], $e->getMessage(), $e->getCode());
        }
    }

    /**
     * 响应json格式数据
     *
     * @param null $data
     * @param string $message
     * @param int $status
     * @param null $callback
     * @return array
     */
    public function outputJson(
        $data = null,
        $message = '',
        $status = 200,
        $callback = null
    ) {
        $this->output->outputJson($data, $message, $status, $callback);
    }
}
