<?php
/**
 * http请求客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Client;

use PG\MSF\Server\CoreBase\CoroutineBase;

class HttpClientRequestCoroutine extends CoroutineBase
{
    /**
     * @var HttpClient
     */
    public $httpClient;
    public $data;
    public $path;
    public $method;

    public function __construct($httpClient, $method, $path, $data, $timeout)
    {
        parent::__construct($timeout);
        $this->httpClient = $httpClient;
        $this->path = $path;
        $this->method = $method;
        $this->data = $data;
        $this->send(function ($client) {
            $this->result = (array)$client;
        });
    }

    public function send($callback)
    {
        switch ($this->method) {
            case 'POST':
                $this->httpClient->post($this->path, $this->data, $callback);
                break;
            case 'GET':
                $this->httpClient->get($this->path, $this->data, $callback);
                break;
        }
    }

}