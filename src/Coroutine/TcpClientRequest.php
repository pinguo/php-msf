<?php

/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Tcp\TcpClient;

class TcpClientRequest extends Base
{
    public $tcpClient;
    public $data;

    public function initialization(TcpClient $tcpClient, string $data, string $path, int $timeout)
    {
        parent::init($timeout);
        $this->tcpClient = $tcpClient;
        $this->data      = $data;
        $profileName     = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#api-tcp:' . $path;
        $logId           = $this->getContext()->getLogId();

        $this->getContext()->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($cli, $recData) use ($profileName, $logId) {
            if (empty(getInstance()->coroutine->taskMap[$logId])) {
                return;
            }
            
            $this->result = $recData;
            $this->responseTime = microtime(true);
            $cli->close();
            $this->getContext()->getLog()->profileEnd($profileName);
            $this->ioBack = true;
            $this->nextRun($logId);
        });

        return $this;
    }

    public function send($callback)
    {
        $this->tcpClient->send($this->data, $callback);
    }

    public function destroy()
    {
        parent::destroy();
    }
}
