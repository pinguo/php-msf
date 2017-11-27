<?php
/**
 * Task的代理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Tasks;

use PG\MSF\Macro;
use PG\MSF\Base\Core;
use PG\MSF\Coroutine\CTask;

/**
 * Class TaskProxy
 * @package PG\MSF\Tasks
 */
class TaskProxy extends Core
{

    /**
     * @var int 任务执行超时时间
     */
    protected $timeout = 0;

    /**
     * @var mixed task执行数据
     */
    private $taskProxyData;

    /**
     * @var int 任务ID
     */
    public $taskId;

    /**
     * @var string 执行的Task Name
     */
    public $taskName;

    /**
     * @var array Task构造参数
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
     * __call魔术方法
     *
     * @param string $name 任务方法名
     * @param array $arguments 执行参数
     * @return CTask
     */
    public function __call($name, $arguments)
    {
        $this->taskProxyData = [
            'type'    => Macro::SERVER_TYPE_TASK,
            'message' => [
                'task_name'      => $this->taskName,
                'task_fuc_name'  => $name,
                'task_fuc_data'  => $arguments,
                'task_context'   => $this->getContext(),
                'task_construct' => $this->taskConstruct,
            ]
        ];

        return $this->getObject(CTask::class, [$this->taskProxyData, $this->timeout]);
    }

    /**
     * 设置任务执行超时时间
     *
     * @param int $timeout 超时时间，单位毫秒
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
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * 异步任务
     *
     * @param callable|null $callback 任务执行完成的回调
     */
    public function startTask($callback = null)
    {
        getInstance()->server->task($this->taskProxyData, -1, $callback);
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        $this->taskProxyData = null;
        parent::destroy();
    }
}
