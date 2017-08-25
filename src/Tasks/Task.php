<?php
/**
 * Task 异步任务
 * 在worker进程通过TaskProxy代理执行请求
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Tasks;

use PG\MSF\Helpers\Context;
use PG\AOP\Wrapper;
use PG\MSF\Memory\Pool;

class Task extends TaskProxy
{
    /**
     * Task constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Tasker进程中初始化任务
     *
     * @param int $taskId
     * @param int $workerPid
     * @param string $taskName
     * @param string $methodName
     * @param Context $context
     * @param Wrapper|Pool $objectPool
     */
    public function __initialization($taskId, $workerPid, $taskName, $methodName, $context, $objectPool)
    {
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
            $context->setObjectPool($objectPool);
            $this->setContext($context);
        }
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        $this->taskId && getInstance()->tidPidTable->del($this->taskId);
        parent::destroy();
        $this->taskId = 0;
    }
}
