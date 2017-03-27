<?php
/**
 * 协程调度器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Server\Coroutine;
use PG\MSF\Server\CoreBase\GeneratorContext;
use PG\MSF\Server\CoreBase\ICoroutineBase;
use Protobuf\Compiler\Generator;

class Scheduler
{
    public $IOCallBack;
    public $taskQueue;
    public $taskMap = [];

    public function __construct()
    {
        $this->taskQueue = new \SplQueue();
        swoole_timer_tick(2, function ($timerId) {
            $this->run();
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

            if ($task->routine->valid() && !($task->routine->current() instanceof ICoroutineBase)) {
                $this->schedule($task);
            }

            if ($task->isFinished()) {
                $task->destroy();
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