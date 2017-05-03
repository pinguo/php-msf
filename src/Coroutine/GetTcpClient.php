<?php
/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright All rights reserved.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Tcp\Client;

class GetTcpClient extends Base
{
    /**
     * @var Client
     */
    public $client;
    public $baseUrl;

    public function __construct(Client $client, $baseUrl, $timeout)
    {
        parent::__construct($timeout);
        $this->baseUrl = $baseUrl;
        $this->client = $client;
        $profileName = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->baseUrl;
        $this->client->context->PGLog->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$this->client->context->PGLog->logId][] = $this;
        $this->send(function ($tcpClient) use ($profileName) {
            $this->result = $tcpClient;
            $this->responseTime = microtime(true);
            if (!empty($this->client->context->PGLog)) {
                $this->client->context->PGLog->profileEnd($profileName);
                $this->ioBack = true;
                $this->nextRun($this->client->context->PGLog->logId);
            }
        });
    }

    public function send($callback)
    {
        $this->client->getTcpClient($this->baseUrl, $callback, $this->timeout);
    }
}