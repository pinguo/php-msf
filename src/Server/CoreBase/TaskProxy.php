<?php
namespace Server\CoreBase;

use Server\SwooleMarco;

/**
 * Task的代理
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午12:11
 */
class TaskProxy extends CoreBase
{
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
    }

    /**
     * 代理
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $this->task_proxy_data =
            [
                'type' => SwooleMarco::SERVER_TYPE_TASK,
                'message' =>
                    [
                        'task_name' => $this->core_name,
                        'task_fuc_name' => $name,
                        'task_fuc_data' => $arguments
                    ]
            ];
    }

    /**
     * 开始异步任务
     */
    public function startTask($callback, $id = -1)
    {
        get_instance()->server->task($this->task_proxy_data, $id, $callback);
    }

    /**
     * 异步的协程模式
     * @param int $id
     * @return TaskCoroutine
     */
    public function coroutineSend($id = -1)
    {
        return new TaskCoroutine($this->task_proxy_data, $id);
    }

    /**
     * 开始同步任务
     */
    public function startTaskWait($timeOurt = 0.5, $id = -1)
    {
        return get_instance()->server->taskwait($this->task_proxy_data, $timeOurt, $id);
    }
}