<?php
/**
 * 协程调度器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Coroutine;

use PG\MSF\Server\Marco;
use PG\MSF\Server\CoreBase\ControllerFactory;
use PG\MSF\Server\CoreBase\ModelFactory;

class Scheduler
{
    public $IOCallBack;
    public $taskQueue;
    public $taskMap = [];
    public $cache;

    public function __construct()
    {
        $this->taskQueue = new \SplQueue();
        swoole_timer_tick(1, function ($timerId) {
            $this->run();
        });

        swoole_timer_tick(1000, function ($timerId) {
            // 当前进程的协程统计信息
            $this->stat();

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

    public function stat()
    {
        $data = [
            // 协程统计信息
            'coroutine' => [
                // 当前正在处理的请求数
                'total' => 0,
            ],
            // 内存使用
            'memory' => [
                // 峰值
                'peak'  => '',
                // 当前使用
                'usage' => '',
            ],
            // 请求信息
            'request' => [
                // 当前Worker进程收到的请求次数
                'worker_request_count' => 0,
            ],
            // 其他对象池
            'object_poll' => [
                // 'xxx' => 22
            ],
            // 控制器对象池
            'controller_poll' => [
                // 'xxx' => 22
            ],
            // Model对象池
            'model_poll' => [
                // 'xxx' => 22
            ],
        ];
        $routineList = getInstance()->coroutine->taskMap;
        $data['coroutine']['total']   = count($routineList);
        $data['memory']['peak_byte']  = memory_get_peak_usage();
        $data['memory']['usage_byte'] = memory_get_usage();
        $data['memory']['peak']       = strval(number_format($data['memory']['peak_byte'] / 1024 / 1024, 3, '.', '')) . 'M';
        $data['memory']['usage']      = strval(number_format($data['memory']['usage_byte'] / 1024 / 1024, 3, '.', '')) . 'M';
        $data['request']['worker_request_count'] = getInstance()->server->stats()['worker_request_count'];

        if (!empty(getInstance()->objectPool->map)) {
            foreach (getInstance()->objectPool->map as $class => $objects) {
                $data['object_poll'][$class] = $objects->count();
            }
        }

        if (!empty(ControllerFactory::getInstance()->pool)) {
            foreach (ControllerFactory::getInstance()->pool as $class => $objects) {
                $data['controller_poll'][$class] = $objects->count();
            }
        }

        if (!empty(ModelFactory::getInstance()->pool)) {
            foreach (ModelFactory::getInstance()->pool as $class => $objects) {
                $data['model_poll'][$class] = $objects->count();
            }
        }

        getInstance()->sysCache->set(Marco::SERVER_STATS . getInstance()->server->worker_id, $data);
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

    public function schedule(CoroutineTask $task)
    {
        $this->taskQueue->enqueue($task);
        return $this;
    }

    public function start(\Generator $routine, GeneratorContext $generatorContext)
    {
        $task = new CoroutineTask($routine, $generatorContext);
        $this->IOCallBack[$generatorContext->getController()->PGLog->logId] = [];
        $this->taskMap[$generatorContext->getController()->PGLog->logId] = $task;
        $this->taskQueue->enqueue($task);
    }
}