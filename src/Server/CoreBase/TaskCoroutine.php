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
        $this->send(function ($serv, $task_id, $data) {
            $this->result = $data;
        });
    }

    public function send($callback)
    {
        get_instance()->server->task($this->task_proxy_data, $this->id, $callback);
    }
}