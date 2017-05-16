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

    public function __construct(TcpClient $tcpClient, string $data, string $path, int $timeout)
    {
        parent::__construct($timeout);
        $this->tcpClient = $tcpClient;
        $this->data = $data;

        $profileName = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#api-tcp:' . $path;
        $this->tcpClient->context->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$this->tcpClient->context->getLogId()][] = $this;
        $this->send(function ($cli, $recData) use ($profileName) {
            $this->result = $recData;
            $this->responseTime = microtime(true);
            $cli->close();
            if (!empty($this->tcpClient->context->getLog())) {
                $this->tcpClient->context->getLog()->profileEnd($profileName);
                $this->ioBack = true;
                $this->nextRun($this->tcpClient->context->getLogId());
            }
        });
    }

    public function send($callback)
    {
        $this->tcpClient->send($this->data, $callback);
    }

    public function destroy()
    {
        unset($this->tcpClient);
        unset($this->data);
    }
}
