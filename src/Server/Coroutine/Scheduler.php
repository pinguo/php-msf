<?php
/**
 * 协程调度器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Server\Coroutine;

class Scheduler
{
    public $IOCallBack;
    public $taskQueue;
    public $taskMap = [];

    public function __construct()
    {
        $this->taskQueue = new \SplQueue();
        swoole_timer_tick(1, function ($timerId) {
            $this->run();
        });

        swoole_timer_tick(1000, function ($timerId) {
            if (empty($this->IOCallBack)) {
                return true;
            }

            foreach ($this->IOCallBack as $logId => $callBacks) {
                foreach ($callBacks as $key => $callBack) {
                    if ($callBack->ioBack) {
                        continue;
                    }

                    if ($callBack->isTimeout()) {
                        $this->schedule($this->taskMap[$logId]);
                    }
                }
            }
        });
    }

    public function schedule(CoroutineTask $task)
    {
        $this->taskQueue->enqueue($task);
        return $this;
    }

    public function run()
    {
        while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->dequeue();
            $task->run();
            if (empty($task->routine)) {
                continue;
            }
            if ($task->routine->valid() && ($task->routine->current() instanceof ICoroutineBase)) {
            } else {
                if ($task->isFinished()) {
                    $task->destroy();
                } else {
                    $this->schedule($task);
                }
            }
        }
    }

    public function start(\Generator $routine, GeneratorContext $generatorContext)
    {
        $task = new CoroutineTask($routine, $generatorContext);
        $this->IOCallBack[$generatorContext->getController()->PGLog->logId] = [];
        $this->taskMap[$generatorContext->getController()->PGLog->logId]    = $task;
        $this->taskQueue->enqueue($task);
    }
}