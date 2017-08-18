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

    /**
     * 任务执行超时时间
     *
     * @var int
     */
    protected $timeout = 0;

    /**
     * 任务ID
     *
     * @var int
     */
    protected $taskId;

    /**
     * task执行数据
     *
     * @var mixed
     */
    private $taskProxyData;

    /**
     * 执行的Task Name
     *
     * @var string
     */
    public $taskName;

    /**
     * Task构造参数
     */
    public $taskConstruct;

    /**
     * TaskProxy constructor.
     * @param array ...$args
     */
    public function __construct(...$args)
    {
        $this->taskConstruct = $args;
        parent::__construct();
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
                'task_name'      => $this->taskName,
                'task_fuc_name'  => $name,
                'task_fuc_data'  => $arguments,
                'task_id'        => $this->taskId,
                'task_context'   => $this->getContext(),
                'task_construct' => $this->taskConstruct,
            ]
        ];

        return $this->getContext()->getObjectPool()->get(CTask::class)->initialization($this->taskProxyData, -1, $this->timeout);
    }

    /**
     * 设置任务执行超时时间
     *
     * @param int $timeout
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 获取任务执行超时时间
     *
     * @param int $timeout
     * @return int
     */
    public function getTimeout($timeout)
    {
        return $this->timeout;
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
     *
     * @param int $timeout 超时时间,单位毫秒
     * @return CTask
     */
    public function coroutineSend($timeout = 0)
    {
        return $this->getContext()->getObjectPool()->get(CTask::class)->initialization($this->taskProxyData, -1, $timeout);
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
        $this->taskId        = null;
        $this->taskProxyData = null;
        parent::destroy();
    }
}
