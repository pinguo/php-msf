<?php
/**
 * TaskCoroutine
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

use PG\MSF\Server\Coroutine\CoroutineBase;

class TaskCoroutine extends CoroutineBase
{
    public $id;
    public $taskProxyData;

    public function __construct($taskProxyData, $id)
    {
        parent::__construct();
        $this->taskProxyData = $taskProxyData;
        $this->id = $id;
        $logId = $taskProxyData['message']['task_context']->logId;
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($serv, $taskId, $data) use ($logId) {
            $this->result = $data;
            $this->ioBack = true;
            $this->nextRun($logId);
        });
    }

    public function send($callback)
    {
        getInstance()->server->task($this->taskProxyData, $this->id, $callback);
    }
}