<?php
/**
 * TaskCoroutine
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

class TaskCoroutine extends CoroutineBase
{
    public $id;
    public $task_proxy_data;

    public function __construct($task_proxy_data, $id)
    {
        parent::__construct();
        $this->task_proxy_data = $task_proxy_data;
        $this->id = $id;
        $logId    = $task_proxy_data['message']['task_context']->logId;
        get_instance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($serv, $task_id, $data) use ($logId) {
            $this->result = $data;
            $this->ioBack = true;
            $this->nextRun($logId);
        });
    }

    public function send($callback)
    {
        get_instance()->server->task($this->task_proxy_data, $this->id, $callback);
    }
}