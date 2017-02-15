<?php
/**
 * 协程http客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Client;

use PG\MSF\Server\CoreBase\CoroutineBase;

class GetHttpClientCoroutine extends CoroutineBase
{
    /**
     * @var Client
     */
    public $client;
    public $base_url;

    public function __construct($client, $base_url)
    {
        parent::__construct();
        $this->base_url = $base_url;
        $this->client = $client;
        $this->send(function ($http_client) {
            $this->result = $http_client;
        });
    }

    public function send($callback)
    {
        $this->client->getHttpClient($this->base_url, $callback);
    }
}