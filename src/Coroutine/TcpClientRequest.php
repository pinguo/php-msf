<?php

/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright All rights reserved.
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
        $logId           = $this->tcpClient->context->getLogId();

        $this->tcpClient->context->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($cli, $recData) use ($profileName, $logId) {
            $this->result = $recData;
            $this->responseTime = microtime(true);
            $cli->close();
            if (!empty($this->tcpClient) && !empty($this->tcpClient->context->getLog())) {
                $this->tcpClient->context->getLog()->profileEnd($profileName);
                $this->ioBack = true;
                $this->nextRun($logId);
            }
        });

        return $this;
    }

    public function send($callback)
    {
        $this->tcpClient->send($this->data, $callback);
    }

    public function destroy()
    {
        unset($this->tcpClient);
        unset($this->data);
        parent::destroy();
    }
}
