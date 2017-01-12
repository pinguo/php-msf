<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Client;

use Server\CoreBase\CoroutineBase;

class HttpClientRequestCoroutine extends CoroutineBase
{
    /**
     * @var HttpClient
     */
    public $httpClient;
    public $data;
    public $path;
    public $method;

    public function __construct($httpClient, $method, $path, $data)
    {
        parent::__construct();
        $this->httpClient = $httpClient;
        $this->path = $path;
        $this->method = $method;
        $this->data = $data;
        $this->send(function ($client) {
            $this->result = $client->body;
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