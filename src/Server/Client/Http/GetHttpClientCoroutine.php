<?php
/**
 * 协程http客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Client\Http;

use PG\MSF\Server\CoreBase\CoroutineBase;

class GetHttpClientCoroutine extends CoroutineBase
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
        $this->send(function ($http_client) use ($profileName) {
            $this->result       = $http_client;
            $this->responseTime = microtime(true);
            $this->client->context->PGLog->profileEnd($profileName);
            if (!empty(get_instance()->coroutine->routineList[$this->client->context->PGLog->logId])) {
                get_instance()->coroutine->keepRun[$this->client->context->PGLog->logId] = get_instance()->coroutine->routineList[$this->client->context->PGLog->logId];
            }
        });
    }

    public function send($callback)
    {
        $this->client->getHttpClient($this->base_url, $callback);
    }
}