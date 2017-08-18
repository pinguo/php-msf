<?php
/**
 * Task 异步任务
 * 在worker进程通过TaskProxy代理执行请求，在Tasker进程为单例
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Tasks;

use PG\AOP\Wrapper;
use PG\MSF\Helpers\Context;
use PG\MSF\Base\AOPFactory;

class Task extends TaskProxy
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __initialization($taskId, $workerPid, $taskName, $methodName, $context)
    {
        /**
         * @var Context $context
         */
        $this->taskId = $taskId;
        getInstance()->tidPidTable->set($this->taskId,
            ['pid' => $workerPid, 'des' => "$taskName::$methodName", 'start_time' => time()]);
        if ($context) {
            $PGLog = null;
            $PGLog = clone getInstance()->log;
            $PGLog->logId = $context->getLogId();
            $PGLog->accessRecord['beginTime'] = microtime(true);
            $PGLog->accessRecord['uri'] = $context->getInput()->getPathInfo();
            $PGLog->pushLog('task', $taskName);
            $PGLog->pushLog('method', $methodName);
            defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME . '-task';
            $PGLog->init();
            // 构造请求上下文成员
            $context->setLogId($PGLog->logId);
            $context->setLog($PGLog);
            $context->setObjectPool(AOPFactory::getObjectPool(getInstance()->objectPool, $this));
            $this->setContext($context);
        }
    }

    public function destroy()
    {
        $this->parent == null && $this->getContext()->getLog() && $this->getContext()->getLog()->appendNoticeLog();
        $this->taskId && getInstance()->tidPidTable->del($this->taskId);
        parent::destroy();
        $this->taskId = 0;
    }
}
