<?php
namespace Server\Client;
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-2
 * Time: 下午1:44
 */
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
        $this->client - setCookies($cookies);
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
     * @return HttpClientRequestCoroutine
     */
    public function coroutineGet($path, $query = null)
    {
        return new HttpClientRequestCoroutine($this, 'GET', $path, $query);
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
     * @return HttpClientRequestCoroutine
     */
    public function coroutinePost($path, $data)
    {
        return new HttpClientRequestCoroutine($this, 'POST', $path, $data);
    }
}