<?php declare(strict_types=1);
/**
 * @desc: 控制器基类
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/2/9
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

use PG\MSF\Server\{
    CoreBase\Controller, Helpers\Log\PGLog, SwooleMarco, Helpers\Context
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

        $context                           = new Context();
        $context->logId                    = $this->logId;
        $context->PGLog                    = $this->PGLog;
        $context->httpInput                = $this->httpInput;
        $context->httpOutput               = $this->httpOutput;
        $context->controller               = $this;
        $this->client->context             = $context;
        $this->tcpClient->context          = $context;
        $this->setContext($context);
    }

    public function destroy()
    {
        $this->PGLog->appendNoticeLog();
        parent::destroy();
    }

    /**
     * gen a logId
     * @return string
     */
    public function genLogId()
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            $this->logId = $this->httpInput->getRequestHeader('log_id') ?? '';
        } else {
            $this->logId = $this->clientData->logId ?? '';
        }

        if (!$this->logId) {
            $this->logId = strval(new \MongoId());
        }

        return $this->logId;
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

    public function outputJson($data = null, $message = '', $status = 200, $callback = null)
    {
        $this->httpOutput->outputJson($data, $message, $status, $callback);
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
        $message = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $message .= ' Trace: ' . $e->getTraceAsString();

        if (!empty($e->getPrevious())) {
            $message .= ' Previous trace: ' . $e->getPrevious()->getTraceAsString();
        }

        $this->PGLog->error($message);

        $this->outputJson([], 'error', 500);
    }
}
