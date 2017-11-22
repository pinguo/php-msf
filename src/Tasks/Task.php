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
use PG\MSF\Base\Pool;

/**
 * Class Task
 * @package PG\MSF\Tasks
 */
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
     * @param int $taskId Task ID
     * @param int $workerPid worker pid
     * @param string $taskName 任务类名
     * @param string $methodName 任务类方法
     * @param Context $context 请求上下文对象
     * @param Wrapper|Pool $objectPool 对象池对象
     */
    public function __initialization($taskId, $workerPid, $taskName, $methodName, $context, $objectPool)
    {
        $this->taskId = $taskId;

        if ($context) {
            // 构造请求上下文成员
            $context->setObjectPool($objectPool);
            $this->setContext($context);
        }
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
        $this->taskId = 0;
    }
}
