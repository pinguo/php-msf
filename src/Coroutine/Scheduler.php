<?php
/**
 * 协程调度器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Base\Core;
use PG\MSF\Controllers\Controller;
use PG\MSF\Helpers\Context;

/**
 * Class Scheduler
 * @package PG\MSF\Coroutine
 */
class Scheduler
{
    /**
     * @var array 正在运行的IO协程对象列表
     */
    public $IOCallBack = [];

    /**
     * @var array 所有正在调度的协程任务（即请求）
     */
    public $taskMap = [];

    /**
     * 初始化协程调度器
     */
    public function __construct()
    {
        /**
         * 每隔1s检查超时的协程
         */
        getInstance()->sysTimers[] = swoole_timer_tick(1000, function ($timerId) {
            if (empty($this->IOCallBack)) {
                return true;
            }

            foreach ($this->IOCallBack as $logId => $callBacks) {
                foreach ($callBacks as $key => $callBack) {
                    if ($callBack->ioBack) {
                        continue;
                    }

                    if ($callBack->isTimeout()) {
                        if (!empty($this->taskMap[$logId])) {
                            $this->schedule($this->taskMap[$logId]);
                        }
                    }
                }
            }
        });

        /**
         * 每隔300s清理对象池中的对象
         */
        swoole_timer_tick(300000, function ($timerId) {
            if (!empty(getInstance()->objectPool->map)) {
                foreach (getInstance()->objectPool->map as $class => &$objectsMap) {
                    while ($objectsMap->count()) {
                        $obj = $objectsMap->shift();
                        if ($obj instanceof Core) {
                            $obj->setRedisPools(null);
                            $obj->setRedisProxies(null);
                        }

                        if ($obj instanceof Controller) {
                            $obj->getObjectPool()->destroy();
                            $obj->setObjectPool(null);
                        }
                        $obj = null;
                        unset($obj);
                    }
                }
            }
        });
    }

    /**
     * 调度协程任务（请求）
     *
     * @param Task $task 协程实例
     * @return $this
     */
    public function schedule(Task $task)
    {
        /* @var $task Task */
        $task->run();

        try {
            do {
                // 协程异步IO
                if ($task->getRoutine()->valid() && ($task->getRoutine()->current() instanceof IBase)) {
                    break;
                }

                // 继续调度
                if (!$task->isFinished()) {
                    $this->schedule($task);
                    break;
                }

                // 请求调度结束（考虑是否回调）
                $task->resetRoutine();
                if (is_callable($task->getCallBack())) {
                    $task->getCallBack()();
                    $task->resetCallBack();
                    break;
                }
            } while (0);
        } catch (\Throwable $e) {
            $task->setException($e);
            $this->schedule($task);
        }

        return $this;
    }


    /**
     * 开始执行调度请求
     *
     * @param \Generator $routine 待调度的迭代器实例
     * @param Context $context 请求的上下文对象
     * @param Controller $controller 当前请求控制器名称
     * @param callable|null $callBack 迭代器执行完成后回调函数
     */
    public function start(\Generator $routine, Context $context, Controller $controller, callable $callBack = null)
    {
        $task = $context->getObjectPool()->get(Task::class, [$routine, $context, $controller, $callBack]);
        $this->IOCallBack[$context->getLogId()] = [];
        $this->taskMap[$context->getLogId()]    = $task;
        $this->schedule($task);
    }
}
