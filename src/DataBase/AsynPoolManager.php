<?php
/**
 * 异步连接池管理器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\DataBase;


use Server\SwooleMarco;
use Server\SwooleServer;

class AsynPoolManager
{
    /**
     * @var SwooleServer
     */
    protected $swooleServer;
    protected $process;
    protected $registDir = [];
    protected $notEventAdd = false;

    public function __construct($process, $swooleServer)
    {
        $this->process = $process;
        $this->swooleServer = $swooleServer;
    }

    /**
     * 采用额外进程的方式
     * event_add
     */
    public function eventAdd()
    {
        $this->notEventAdd = false;
        swoole_event_add($this->process->pipe, [$this, 'getPipeMessage']);
    }

    /**
     * 不采用进程通讯，每个进程都启用进程池
     */
    public function noEventAdd()
    {
        $this->notEventAdd = true;
    }

    /**
     * 管道来消息
     * @param $pipe
     */
    public function getPipeMessage($pipe)
    {
        $read = $this->process->read();
        $baodata = unserialize($read);
        $asyn = $this->registDir[$baodata['asyn_name']];
        call_user_func([$asyn, 'execute'], $baodata);
    }

    /**
     * 分发消息
     * @param $data
     */
    public function distribute($data)
    {
        $asyn = $this->registDir[$data['asyn_name']];
        call_user_func([$asyn, 'distribute'], $data);
    }

    /**
     * 注册
     * @param $asyn
     */
    public function registAsyn(IAsynPool $asyn)
    {
        $this->registDir[$asyn->getAsynName()] = $asyn;
        $asyn->serverInit($this->swooleServer, $this);
    }

    /**
     * 写入管道
     * @param IAsynPool $asyn
     * @param $data
     * @param $workerId
     */
    public function writePipe(IAsynPool $asyn, $data, $workerId)
    {
        if ($this->notEventAdd) {
            call_user_func([$asyn, 'execute'], $data);
        } else {
            $data['asyn_name'] = $asyn->getAsynName();
            $data['worker_id'] = $workerId;
            //写入管道
            $this->process->write(serialize($data));
        }
    }

    /**
     * 分发消息给worker
     * @param $data
     */
    public function sendMessageToWorker(IAsynPool $asyn, $data)
    {
        if ($this->notEventAdd) {
            call_user_func([$asyn, 'distribute'], $data);
        } else {
            $workerID = $data['worker_id'];
            $message = $this->swooleServer->packSerevrMessageBody(SwooleMarco::MSG_TYPR_ASYN, $data);
            $this->swooleServer->server->sendMessage($message, $workerID);
        }

    }
}