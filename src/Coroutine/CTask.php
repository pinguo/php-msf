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

/**
 * Class CTask
 * @package PG\MSF\Coroutine
 */
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
     * @param array $taskProxyData 待执行的Task信息
     * @param int $timeout 超时时间，单位秒
     */
    public function __construct($taskProxyData, $timeout)
    {
        parent::__construct($timeout);
        $this->taskProxyData = $taskProxyData;
        $profileName         = $taskProxyData['message']['task_name'] . '::' . $taskProxyData['message']['task_fuc_name'];
        $this->requestId     = $this->getContext()->getRequestId();
        $requestId           = $this->requestId;

        $this->getContext()->getLog()->profileStart($profileName);
        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($serv, $taskId, $data) use ($profileName, $requestId) {
            if (empty($this->getContext()) || ($requestId != $this->getContext()->getRequestId())) {
                return;
            }

            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
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
     * @param callable $callback 任务完成后的回调函数
     * @return $this
     * @throws Exception
     */
    public function send($callback)
    {
        $this->id = getInstance()->server->task($this->taskProxyData, -1, $callback);
        if ($this->id === false) {
            throw new Exception("worker->tasker send async task failed, data: " . dump($this->taskProxyData, false, true));
        }

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
