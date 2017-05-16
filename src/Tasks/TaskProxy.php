<?php
/**
 * Task的代理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Tasks;

use PG\MSF\Marco;
use PG\MSF\Base\Core;
use PG\MSF\Coroutine\CTask;

class TaskProxy extends Core
{
    protected $taskId;
    /**
     * task代理数据
     * @var mixed
     */
    private $taskProxyData;

    /**
     * TaskProxy constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->afterConstruct();
    }

    /**
     * 代理
     * @param $name
     * @param $arguments
     * @return int
     */
    public function __call($name, $arguments)
    {
        $this->taskId = getInstance()->taskAtomic->add();
        //这里设置重置标识，id=65536,便设置回1
        $reset        = getInstance()->taskAtomic->cmpset(65536, 1);

        if ($reset) {
            $this->taskId = 1;
        }

        $this->taskProxyData = [
            'type'    => Marco::SERVER_TYPE_TASK,
            'message' => [
                'task_name'     => $this->coreName,
                'task_fuc_name' => $name,
                'task_fuc_data' => $arguments,
                'task_id'       => $this->taskId,
                'task_context'  => $this->getContext(),
            ]
        ];

        return $this->taskId;
    }

    /**
     * 开始异步任务
     * @param null $callback
     */
    public function startTask($callback = null)
    {
        getInstance()->server->task($this->taskProxyData, -1, $callback);
    }

    /**
     * 异步的协程模式
     * @return CTask
     */
    public function coroutineSend()
    {
        return new CTask($this->taskProxyData, -1);
    }

    /**
     * 开始同步任务
     * @param float $timeOut
     * @return mixed
     */
    public function startTaskWait($timeOut = 0.5)
    {
        return getInstance()->server->taskwait($this->taskProxyData, $timeOut, -1);
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        unset($this->taskProxyData);
        unset($this->taskId);
        parent::destroy();
    }
}
