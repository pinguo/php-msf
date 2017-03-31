<?php
/**
 * http请求客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Client\Http;

use PG\MSF\Server\CoreBase\CoroutineBase;
use PG\MSF\Server\Coroutine\Scheduler;

class HttpClientRequestCoroutine extends CoroutineBase
{
    /**
     * @var HttpClient
     */
    public $httpClient;
    public $data;
    public $path;
    public $method;

    public function __construct(HttpClient $httpClient, $method, $path, $data, $timeout)
    {
        parent::__construct($timeout);
        $this->httpClient = $httpClient;
        $this->path       = $path;
        $this->method     = $method;
        $this->data       = $data;
        $profileName      =  mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#api-http://' . $this->httpClient->headers['Host'] . $this->path;
        $this->httpClient->context->PGLog->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$this->httpClient->context->PGLog->logId][] = $this;
        $this->send(function ($client) use ($profileName) {
            $this->result       = (array)$client;
            $this->responseTime = microtime(true);
            $this->httpClient->context->PGLog->profileEnd($profileName);
            $this->ioBack = true;
            $this->nextRun($this->httpClient->context->PGLog->logId);
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