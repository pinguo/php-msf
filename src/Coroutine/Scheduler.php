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

            foreach ($this->IOCallBack as $requestId => $callBacks) {
                foreach ($callBacks as $key => $callBack) {
                    if ($callBack->ioBack) {
                        continue;
                    }

                    if ($callBack->isTimeout()) {
                        if (!empty($this->taskMap[$requestId])) {
                            $this->schedule($this->taskMap[$requestId]);
                        }
                    }
                }
            }
        });

        /**
         * 每隔3600s清理对象池中的对象
         */
        swoole_timer_tick(3600000, function ($timerId) {
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
     * @param callable|null $callback 特殊场景回调，如清理资源
     * @return $this
     */
    public function schedule(Task $task, callable $callback = null)
    {
        // 特殊场景回调，如清理资源
        if ($callback !== null) {
            $task->resetCallBack($callback);
        }

        /* @var $task Task */
        $task->run();

        try {
            do {
                // 迭代器检查
                if (!$task->getRoutine() instanceof \Generator) {
                    break;
                }

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
                    $func = $task->getCallBack();
                    $task->resetCallBack();
                    $func();
                    break;
                }
            } while (0);
        } catch (\Throwable $e) {
            $task->handleTaskException($e, null);
            $this->schedule($task);
        }

        return $this;
    }


    /**
     * 开始执行调度请求
     *
     * @param \Generator $routine 待调度的迭代器实例
     * @param Controller $controller 当前请求控制器名称
     * @param callable|null $callBack 迭代器执行完成后回调函数
     */
    public function start(\Generator $routine, Controller $controller, callable $callBack = null)
    {
        $task  = $controller->getObject(Task::class, [$routine, $controller, $callBack]);
        $requestId = $controller->getContext()->getRequestId();
        $this->IOCallBack[$requestId] = [];
        $this->taskMap[$requestId]    = $task;
        $this->schedule($task);
    }
}
