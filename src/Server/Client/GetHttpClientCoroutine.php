<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Client;

use Server\CoreBase\CoroutineBase;

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