<?php
/**
 * http客户端,支持协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Server\Client;

class Client
{
    /**
     * 上下文
     * 目前包含 ['PGLog']
     * @var array
     */
    public $context = [];

    /**
     * 获取一个http客户端
     * @param $base_url
     * @param $callBack
     * @throws \PG\MSF\Server\CoreBase\SwooleException
     */
    public function getHttpClient($base_url, $callBack)
    {
        $data = [];
        $data['url'] = $base_url;
        $data['callBack'] = $callBack;
        $data['port'] = 80;
        $data['ssl'] = false;
        $parseBaseUrlResult = explode(":", $base_url);

        if (count($parseBaseUrlResult) == 2) {
            $url_head = $parseBaseUrlResult[0];
            $url_host = $parseBaseUrlResult[1];
        } elseif (count($parseBaseUrlResult) == 3) {
            $url_head = $parseBaseUrlResult[0];
            $url_host = $parseBaseUrlResult[1];
            $url_port = $parseBaseUrlResult[2];
        } else {
            throw new \PG\MSF\Server\CoreBase\SwooleException($base_url . '不合法,请检查配置或者参数');
        }

        if (!empty($url_port)) {
            $data['port'] = $url_port;
        } else {
            if ($url_head == "https") {
                $data['port'] = 443;
            }
        }

        if ($url_head == 'https') {
            $data['ssl'] = true;
        }

        $url_host = substr($url_host, 2);
        swoole_async_dns_lookup($url_host, function ($host, $ip) use (&$data) {
            if (empty($ip)) {
                // 异步回调的异常捕获是一个问题,暂时没有解决
                // throw new \PG\MSF\Server\CoreBase\SwooleException($data['url'] . ' DNS查询失败');
            } else {
                $client = new \swoole_http_client($ip, $data['port'], $data['ssl']);
                $http_client = new HttpClient($client);
                $headers = ['Host' => $host];
                if (isset($this->context['PGLog'])) {
                    $PGLog = $this->context['PGLog'];
                    $headers['X-Ngx-LogId'] = $PGLog->logId;
                }
                $http_client->setHeaders($headers);
                call_user_func($data['callBack'], $http_client);
            }
        });
    }

    /**
     * 协程方式获取httpclient
     *
     * @param $base_url
     * @param int $timeout 协程超时时间
     * @return GetHttpClientCoroutine
     */
    public function coroutineGetHttpClient($base_url, $timeout = 1000)
    {
        return new GetHttpClientCoroutine($this, $base_url, $timeout);
    }
}