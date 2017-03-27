<?php
/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Client\Tcp;


use PG\MSF\Server\CoreBase\CoroutineBase;

class GetTcpClientCoroutine extends CoroutineBase
{
    /**
     * @var Client
     */
    public $client;
    public $base_url;

    public function __construct(Client $client, $base_url, $timeout)
    {
        parent::__construct($timeout);
        $this->base_url = $base_url;
        $this->client   = $client;
        $profileName    =  mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->base_url;
        $this->client->context->PGLog->profileStart($profileName);
        $this->send(function ($tcpClient) use ($profileName) {
            $this->result       = $tcpClient;
            $this->responseTime = microtime(true);
            $this->client->context->PGLog->profileEnd($profileName);
        });
    }

    public function send($callback)
    {
        $this->client->getTcpClient($this->base_url, $callback, $this->timeout);
    }
}