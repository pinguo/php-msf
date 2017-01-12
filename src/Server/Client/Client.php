<?php
namespace Server\Client;
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-2
 * Time: 下午2:54
 */
class Client
{
    /**
     * 获取一个http客户端
     * @param $base_url
     * @param $callBack
     */
    public function getHttpClient($base_url, $callBack)
    {
        $data = [];
        $data['url'] = $base_url;
        $data['callBack'] = $callBack;
        $data['port'] = 80;
        $data['ssl'] = false;
        list($url_head, $url_host, $url_port) = explode(":", $base_url);
        if ($url_head == "https") {
            $data['ssl'] = true;
            $data['port'] = 443;
        }
        if (!empty($url_port)) {
            $data['port'] = $url_port;
        }
        $url_host = substr($url_host, 2);
        swoole_async_dns_lookup($url_host, function ($host, $ip) use (&$data) {
            $client = new \swoole_http_client($ip, $data['port'], $data['ssl']);
            $http_client = new HttpClient($client);
            call_user_func($data['callBack'], $http_client);
        });
    }

    /**
     * 协程方式获取httpclient
     * @param $base_url
     * @return GetHttpClientCoroutine
     */
    public function coroutineGetHttpClient($base_url)
    {
        return new GetHttpClientCoroutine($this, $base_url);
    }
}