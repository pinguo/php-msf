<?php
/**
 * Task的代理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

use PG\MSF\Server\SwooleMarco;

class TaskProxy extends CoreBase
{
    protected $task_id;
    /**
     * task代理数据
     * @var mixed
     */
    private $task_proxy_data;

    /**
     * TaskProxy constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->afterConstruct();
    }

    public function initialization($task_id, $worker_pid, $task_name, $method_name, $context)
    {
        $this->setContext($context);
    }

    /**
     * 代理
     * @param $name
     * @param $arguments
     * @return int
     */
    public function __call($name, $arguments)
    {
        $this->task_id = get_instance()->task_atomic->add();
        //这里设置重置标识，id=65536,便设置回1
        $reset = get_instance()->task_atomic->cmpset(65536, 1);
        if ($reset) {
            $this->task_id = 1;
        }
        $this->task_proxy_data =
            [
                'type' => SwooleMarco::SERVER_TYPE_TASK,
                'message' =>
                    [
                        'task_name' => $this->core_name,
                        'task_fuc_name' => $name,
                        'task_fuc_data' => $arguments,
                        'task_id' => $this->task_id,
                        'task_context' => $this->getContext(),
                    ]
            ];
        return $this->task_id;
    }

    /**
     * 开始异步任务
     * @param null $callback
     */
    public function startTask($callback = null)
    {
        get_instance()->server->task($this->task_proxy_data, -1, $callback);
    }

    /**
     * 异步的协程模式
     * @return TaskCoroutine
     */
    public function coroutineSend()
    {
        return new TaskCoroutine($this->task_proxy_data, -1);
    }

    /**
     * 开始同步任务
     */
    public function startTaskWait($timeOut = 0.5)
    {
        return get_instance()->server->taskwait($this->task_proxy_data, $timeOut, -1);
    }
}