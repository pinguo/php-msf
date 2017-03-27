<?php

/**
 * @desc:
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Client\Tcp;

use PG\MSF\Server\CoreBase\{
    CoroutineBase
};

class TcpClientRequestCoroutine extends CoroutineBase
{
    public $tcpClient;
    public $data;

    public function __construct(TcpClient $tcpClient, $data, $timeout)
    {
        parent::__construct($timeout);
        $this->tcpClient = $tcpClient;
        $this->data = $data;

        $profileName = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#api-tcp://'; //todo
        //$this->tcpClient->context->PGLog->profileStart($profileName);
        $this->send(function ($cli, $recData) use ($profileName) {
            $this->result = $recData;
            $this->responseTime = microtime(true);
            $cli->close();
            //$this->tcpClient->context->PGLog->profileEnd($profileName);
        });
    }

    public function send($callback)
    {
        $this->tcpClient->send($this->data, $callback);
    }
}