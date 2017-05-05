<?php
/**
 * Console Controller基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Console;

use PG\MSF\Controllers\Controller as BController;
use PG\Log\PGLog;
use PG\MSF\Marco;
use PG\MSF\Helpers\Context;

class Controller extends BController
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
        //$this->PGLog->accessRecord['uri'] = str_replace('\\', '/', '/' . $controllerName . '/' . $methodName);
        $this->PGLog->accessRecord['uri'] = $this->input->getPathInfo();
        $this->PGLog->logId = $this->genLogId();
        defined('SYSTEM_NAME') && $this->PGLog->channel = SYSTEM_NAME;
        $this->PGLog->init();
        $this->PGLog->pushLog('controller', $controllerName);
        $this->PGLog->pushLog('method', $methodName);

        $context = $this->objectPool->get(Context::class);
        $context->logId           = $this->logId;
        $context->PGLog           = $this->PGLog;
        $context->input           = $this->input;
        $context->output          = $this->output;
        $context->controller      = $this;
        $this->client->context    = $context;
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
        $this->PGLog->pushLog('params', $this->input->getAllPostGet());
        $this->PGLog->pushLog('status', '200');
        $this->PGLog->appendNoticeLog();
        $timers = getInstance()->sysTimers;
        if (!empty($timers)) {
            foreach ($timers as $timerId) {
                swoole_timer_clear($timerId);
            }
        }
        parent::destroy();
        swoole_event_exit();
        exit();
    }
}
