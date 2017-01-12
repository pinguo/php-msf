<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\CoreBase;
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