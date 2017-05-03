<?php
/**
 * http客户端,支持协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Client\Http;

use PG\MSF\{
    Base\Exception, Helpers\Context, Coroutine\GetHttpClient
};

class Client
{
    /**
     * 上下文
     * @var Context
     */
    public $context;

    /**
     * 获取一个http客户端
     * @param $baseUrl
     * @param $callBack
     * @throws \PG\MSF\Base\Exception
     */
    public function getHttpClient($baseUrl, $callBack, array $headers = [])
    {
        $data = [];
        $data['url'] = $baseUrl;
        $data['callBack'] = $callBack;
        $data['port'] = 80;
        $data['ssl'] = false;
        $parseBaseUrlResult = explode(":", $baseUrl);

        if (count($parseBaseUrlResult) == 2) {
            $urlHead = $parseBaseUrlResult[0];
            $urlHost = $parseBaseUrlResult[1];
        } elseif (count($parseBaseUrlResult) == 3) {
            $urlHead = $parseBaseUrlResult[0];
            $urlHost = $parseBaseUrlResult[1];
            $urlPort = $parseBaseUrlResult[2];
        } else {
            throw new Exception($baseUrl . ' 不合法,请检查配置或者参数');
        }

        if (!empty($urlPort)) {
            $data['port'] = $urlPort;
        } else {
            if ($urlHead == "https") {
                $data['port'] = 443;
            }
        }

        if ($urlHead == 'https') {
            $data['ssl'] = true;
        }

        $urlHost = substr($urlHost, 2);
        swoole_async_dns_lookup($urlHost, function ($host, $ip) use (&$data, &$headers) {
            if ($ip === '127.0.0.0') { // fix bug
                $ip = '127.0.0.1';
            }
            if (empty($ip)) {
                $this->context->PGLog->warning($data['url'] . ' DNS查询失败');
                $this->context->output->end();
            } else {
                if (empty($this->context) || empty($this->context->PGLog)) {
                    return true;
                }
                
                $client = new \swoole_http_client($ip, $data['port'], $data['ssl']);
                $httpClient = new HttpClient($client);
                $httpClient->context = $this->context;
                $headers = array_merge($headers, [
                    'Host' => $host,
                    'X-Ngx-LogId' => $this->context->PGLog->logId,
                ]);

                $httpClient->setHeaders($headers);
                call_user_func($data['callBack'], $httpClient);
            }
        });
    }

    /**
     * 协程方式获取httpClient
     *
     * @param $baseUrl
     * @param int $timeout 协程超时时间
     * @return GetHttpClient
     */
    public function coroutineGetHttpClient($baseUrl, $timeout = 30000, $headers = [])
    {
        return new GetHttpClient($this, $baseUrl, $timeout, $headers);
    }
}