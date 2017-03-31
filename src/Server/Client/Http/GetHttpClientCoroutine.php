<?php
/**
 * 协程http客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Client\Http;

use PG\MSF\Server\CoreBase\CoroutineBase;
use PG\MSF\Server\Coroutine\Scheduler;

class GetHttpClientCoroutine extends CoroutineBase
{
    /**
     * @var Client
     */
    public $client;
    public $baseUrl;
    public $headers;

    public function __construct(Client $client, $baseUrl, $timeout, $headers = [])
    {
        parent::__construct($timeout);
        $this->baseUrl = $baseUrl;
        $this->client  = $client;
        $this->headers = $headers;
        $profileName    =  mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->baseUrl;
        $this->client->context->PGLog->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$this->client->context->PGLog->logId][] = $this;
        $this->send(function ($httpClient) use ($profileName) {
            $this->result       = $httpClient;
            $this->responseTime = microtime(true);
            $this->client->context->PGLog->profileEnd($profileName);
            $this->ioBack = true;
            $this->nextRun($this->client->context->PGLog->logId);
        });
    }

    public function send($callback)
    {
        $this->client->getHttpClient($this->baseUrl, $callback, $this->headers);
    }
}