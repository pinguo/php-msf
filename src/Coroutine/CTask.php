<?php
/**
 * Task协程
 *
 * 由worker进程投递到tasker进程的任务
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

class CTask extends Base
{
    /**
     * @var int 任务ID
     */
    public $id;

    /**
     * @var array 任务执行参数
     */
    public $taskProxyData;

    /**
     * 初始化Task协程对象
     *
     * @param array $taskProxyData
     * @param int $id
     * @param int $timeout
     */
    public function __construct($taskProxyData, $id, $timeout)
    {
        parent::__construct($timeout);
        $this->taskProxyData = $taskProxyData;
        $this->id            = $id;
        $profileName         = $taskProxyData['message']['task_name'] . '::' . $taskProxyData['message']['task_fuc_name'];
        $this->requestId     = $this->getContext()->getLogId();

        $this->getContext()->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->coroutine->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($serv, $taskId, $data) use ($profileName) {
            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->coroutine->taskMap[$this->requestId])) {
                return;
            }

            $this->getContext()->getLog()->profileEnd($profileName);
            $this->result = $data;
            $this->ioBack = true;
            $this->nextRun();
        });
    }

    /**
     * 投递异步任务给Tasker进程
     *
     * @param callable $callback
     * @return $this
     */
    public function send($callback)
    {
        getInstance()->server->task($this->taskProxyData, $this->id, $callback);
        return $this;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
