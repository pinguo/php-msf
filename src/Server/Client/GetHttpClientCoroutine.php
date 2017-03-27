<?php
/**
 * 协程http客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Client;

use PG\MSF\Server\CoreBase\CoroutineBase;
use PG\MSF\Server\Coroutine\Scheduler;

class GetHttpClientCoroutine extends CoroutineBase
{
    /**
     * @var Client
     */
    public $client;
    public $base_url;

    public function __construct($client, $base_url, $timeout)
    {
        parent::__construct($timeout);
        $this->base_url = $base_url;
        $this->client   = $client;
        $profileName    =  mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->base_url;
        $this->client->context->PGLog->profileStart($profileName);
        get_instance()->coroutine->IOCallBack[$this->client->context->PGLog->logId][] = $this;
        $this->send(function ($http_client) use ($profileName) {
            $this->result       = $http_client;
            $this->responseTime = microtime(true);
            $this->client->context->PGLog->profileEnd($profileName);
            $this->ioBack = true;
            $this->nextRun($this->client->context->PGLog->logId);
        });
    }

    public function send($callback)
    {
        $this->client->getHttpClient($this->base_url, $callback);
    }
}