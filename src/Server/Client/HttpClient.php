<?php
/**
 * http客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Client;

class HttpClient
{
    public $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @param $headers
     */
    public function setHeaders($headers)
    {
        $this->client->setHeaders($headers);
    }

    /**
     * @param $cookies
     */
    public function setCookies($cookies)
    {
        $this->client->setCookies($cookies);
    }

    /**
     * @param $path
     * @param $query
     * @param $callback
     */
    public function get($path, $query, $callback)
    {
        if (!empty($query)) {
            $path = $path . "?" . http_build_query($query);
        }
        $this->client->get($path, $callback);
    }

    /**
     * 协程方式Get
     * @param $path
     * @param $query
     * @param $timeout int 超时时间
     * @return HttpClientRequestCoroutine
     */
    public function coroutineGet($path, $query = null, $timeout = 1000)
    {
        return new HttpClientRequestCoroutine($this, 'GET', $path, $query, $timeout);
    }

    /**
     * @param $path
     * @param $data
     * @param $callback
     */
    public function post($path, $data, $callback)
    {
        $this->client->post($path, $data, $callback);
    }

    /**
     * 协程方式Post
     * @param $path
     * @param $data
     * @param $timeout int 超时时间
     * @return HttpClientRequestCoroutine
     */
    public function coroutinePost($path, $data, $timeout = 1000)
    {
        return new HttpClientRequestCoroutine($this, 'POST', $path, $data, $timeout);
    }
}