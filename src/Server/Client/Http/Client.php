<?php
/**
 * http客户端,支持协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Server\Client\Http;

use PG\MSF\Server\ {
    CoreBase\SwooleException,
    Helpers\Context
};

class Client
{
    /**
     * 上下文
     * @var Context
     */
    public $context;

    public function __construct()
    {
        $this->context = new Context();
    }

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
            throw new SwooleException($base_url . ' 不合法,请检查配置或者参数');
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
                $this->context->PGLog->warning($data['url'] . ' DNS查询失败');
            } else {
                $client = new \swoole_http_client($ip, $data['port'], $data['ssl']);
                $httpClient          = new HttpClient($client);
                $httpClient->context = $this->context;
                $headers = [
                    'Host'        => $host,
                    'X-Ngx-LogId' => $this->context->PGLog->logId,
                ];

                $httpClient->setHeaders($headers);
                call_user_func($data['callBack'], $httpClient);
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