<?php
/**
 * 异步连接池管理器
 *
 * @author tmtbe
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pools;

use PG\MSF\Marco;
use PG\MSF\MSFServer;

class AsynPoolManager
{
    /**
     * @var MSFServer server运行实例
     */
    protected $swooleServer;

    /**
     * @var \swoole_process 连接池进程
     */
    protected $process;

    /**
     * @var array 已注册连接池
     */
    protected $registerDir = [];

    /**
     * @var bool
     */
    protected $notEventAdd = false;

    /**
     * AsynPoolManager constructor.
     *
     * @param \swoole_process $process
     * @param MSFServer $swooleServer
     */
    public function __construct($process, $swooleServer)
    {
        $this->process      = $process;
        $this->swooleServer = $swooleServer;
    }

    /**
     * 采用额外进程的方式
     *
     * @return $this
     */
    public function eventAdd()
    {
        $this->notEventAdd = false;
        swoole_event_add($this->process->pipe, [$this, 'getPipeMessage']);

        return $this;
    }

    /**
     * 不采用进程通讯，每个进程都启用进程池
     *
     * @return $this
     */
    public function noEventAdd()
    {
        $this->notEventAdd = true;

        return $this;
    }

    /**
     * 算是管道消息
     *
     * @param $pipe
     * @return $this
     */
    public function getPipeMessage($pipe)
    {
        $read = $this->process->read();
        $data = unserialize($read);
        $asyn = $this->registerDir[$data['asyn_name']];
        $asyn->execute($data);

        return $this;
    }

    /**
     * 分发消息
     *
     * @param $data
     * @return $this
     */
    public function distribute($data)
    {
        $asyn = $this->registerDir[$data['asyn_name']];
        $asyn->distribute($data);

        return $this;
    }

    /**
     * 注册异步连接池
     *
     * @param $asyn
     * @return $this
     */
    public function registerAsyn(IAsynPool $asyn)
    {
        $this->registerDir[$asyn->getAsynName()] = $asyn;
        $asyn->serverInit($this->swooleServer, $this);

        return $this;
    }

    /**
     * 写入管道
     *
     * @param IAsynPool $asyn
     * @param $data
     * @param $workerId
     * @return $this
     */
    public function writePipe(IAsynPool $asyn, $data, $workerId)
    {
        if ($this->notEventAdd) {
            $asyn->execute($data);
        } else {
            $data['asyn_name'] = $asyn->getAsynName();
            $data['worker_id'] = $workerId;
            //写入管道
            $this->process->write(serialize($data));
        }

        return $this;
    }

    /**
     * 分发消息给worker
     *
     * @param $data
     * @return $this
     */
    public function sendMessageToWorker(IAsynPool $asyn, $data)
    {
        if ($this->notEventAdd) {
            $asyn->distribute($data);
        } else {
            $workerID = $data['worker_id'];
            $message  = $this->swooleServer->packSerevrMessageBody(Marco::MSG_TYPR_ASYN, $data);
            $this->swooleServer->server->sendMessage($message, $workerID);
        }

        return $this;
    }
}
