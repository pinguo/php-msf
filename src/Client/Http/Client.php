<?php
/**
 * http客户端,支持协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Client\Http;

use Exception;
use PG\MSF\Base\Core;
use PG\MSF\Helpers\Context;
use PG\MSF\Coroutine\GetHttpClient;

class Client extends Core
{
    /**
     * @var array DNS查询缓存
     */
    public static $dnsCache;

    /**
     * 获取一个http客户端
     * @param array | string $baseUrl
     * @param $callBack
     * @throws \Exception
     */
    public function getHttpClient($baseUrl, $callBack, array $headers = [])
    {
        if (is_array($baseUrl)) {
            $data = $baseUrl;
        } else {
            $data = $this->parseUrl($baseUrl);
            if (!$data) {
                throw new Exception($baseUrl . ' 不合法,请检查配置或者参数');
            }
        }

        $data['callBack'] = $callBack;
        $data['headers']  = $headers;

        $ip = self::getDnsCache($data['host']);
        if ($ip !== null) {
            $this->coroutineCallBack($ip, $data);
        } else {
            $logId = $this->getContext()->getLogId();
            swoole_async_dns_lookup($data['host'], function ($host, $ip) use (&$data, $logId) {
                if ($ip === '127.0.0.0') { // fix bug
                    $ip = '127.0.0.1';
                }

                if (empty(getInstance()->coroutine->taskMap[$logId])) {
                    return;
                }

                if (empty($ip)) {
                    $this->getContext()->getLog()->warning($data['url'] . ' DNS查询失败');
                } else {
                    self::setDnsCache($host, $ip);
                    $this->coroutineCallBack($ip, $data);
                }
            });
        }
    }

    /**
     * 获取协程GetHttpClient,未解析DNS
     *
     * @param string $baseUrl  如 https://www.baidu.com
     * @param int $timeout 协程超时时间
     * @param array $headers 额外的报头
     * @return GetHttpClient
     */
    public function coroutineGetHttpClient($baseUrl, $timeout = 30000, $headers = [])
    {
        return $this->getContext()->getObjectPool()->get(GetHttpClient::class)->initialization($this, $baseUrl, $timeout, $headers);
    }

    /**
     * 以协程方式获取异步的HttpClient,已解析DNS
     *
     * @param string $baseUrl  如 https://www.baidu.com
     * @param int $timeout 协程超时时间
     * @param array $headers 额外的报头
     * @return HttpClient
     */
    public function coroutineHttpClientWithDNS($baseUrl, $timeout = 30000, $headers = [])
    {
        $sendDnsQuery = $this->getContext()->getObjectPool()->get(GetHttpClient::class)->initialization($this, $baseUrl, 300, $headers);
        /**
         * @var $httpClient HttpClient
         */
        $httpClient = yield $sendDnsQuery;

        return $httpClient;
    }

    /**
     * 以协程方式发送POST请求,包含DNS解析、获取数据
     *
     * @param string $url 请求的URL
     * @param array $data POST的数据
     * @param int $timeout 请求超时时间
     * @param array $headers 额外的报头
     * @return bool|array
     */
    public function coroutinePost($url, $data = [], $timeout = 30000, $headers = [])
    {
        /**
         * @var $httpClient HttpClient
         */
        $tmp = $this->getHttpClientOrDnsQuery($url, $headers);
        if ($tmp[0] instanceof GetHttpClient) {
            $httpClient = yield $tmp[0];
        } else {
            $httpClient = $tmp[0];
        }

        if (!$httpClient) {
            return false;
        }

        $sendPostReq  = $httpClient->coroutinePost($tmp[1]['path'] . $tmp[1]['query'], $data, $timeout);
        $result       = yield $sendPostReq;

        return $result;
    }

    /**
     * 以协程方式发送GET请求,包含DNS解析、获取数据
     *
     * @param string $url 请求的URL
     * @param array $query POST的数据
     * @param int $timeout 请求超时时间
     * @param array $headers 额外的报头
     * @return bool|array
     */
    public function coroutineGet($url, $query = null, $timeout = 30000, $headers = [])
    {
        /**
         * @var $httpClient HttpClient
         */
        $tmp = $this->getHttpClientOrDnsQuery($url, $headers);
        if ($tmp[0] instanceof GetHttpClient) {
            $httpClient = yield $tmp[0];
        } else {
            $httpClient = $tmp[0];
        }

        if (!$httpClient) {
            return false;
        }

        $sendGetReq  = $httpClient->coroutineGet($tmp[1]['path'] . $tmp[1]['query'], $query, $timeout);
        $result       = yield $sendGetReq;

        return $result;
    }

    protected function parseUrl($url)
    {
        $parseUrlResult = parse_url($url);
        if ($parseUrlResult === false) {
            return false;
        }

        if (empty($parseUrlResult['scheme'])) {
            $parseUrlResult['scheme'] = 'http';
        }

        if (empty($parseUrlResult['host'])) {
            return false;
        }

        $parseUrlResult['url'] = $url;

        if (empty($parseUrlResult['port'])) {
            if ($parseUrlResult['scheme'] == 'http') {
                $parseUrlResult['port'] = 80;
                $parseUrlResult['ssl']  = false;
            } else {
                $parseUrlResult['port'] = 443;
                $parseUrlResult['ssl']  = true;
            }
        }

        if (empty($parseUrlResult['path'])) {
            $parseUrlResult['path'] = '/';
        }

        if (empty($parseUrlResult['query'])) {
            $parseUrlResult['query'] = '';
        } else {
            $parseUrlResult['query'] = '?' . $parseUrlResult['query'];
        }

        return $parseUrlResult;
    }

    /**
     * 解析URL并返回Http Client
     *
     * @param string $url
     * @param array $headers
     * @return GetHttpClient|HttpClient
     * @throws Exception
     */
    protected function getHttpClientOrDnsQuery($url, array $headers = [])
    {
        $parseUrlResult = $this->parseUrl($url);
        if (!$parseUrlResult) {
            throw new Exception($url . ' 不合法,请检查配置或者参数');
        }

        $ip = self::getDnsCache($parseUrlResult['host']);
        if ($ip !== null) {
            $client     = new \swoole_http_client($ip, $parseUrlResult['port'], $parseUrlResult['ssl']);
            $httpClient = $this->getContext()->getObjectPool()->get(HttpClient::class);
            $httpClient->initialization($client);
            $headers = array_merge($headers, [
                'Host'        => $parseUrlResult['host'],
                'X-Ngx-LogId' => $this->context->getLogId(),
            ]);

            $httpClient->setHeaders($headers);
        } else {
            $httpClient = $this->getContext()->getObjectPool()->get(GetHttpClient::class)->initialization($this, $parseUrlResult, 3000, $headers);
        }

        return [$httpClient, $parseUrlResult];
    }

    /**
     * DNS查询返回协程的回调
     *
     * @param $ip string 主机名对应的IP
     * @param $data array
     * @param $dnsCache boolean
     * @return bool
     */
    public function coroutineCallBack($ip, $data)
    {
        if (empty($this->context) || empty($this->context->getLog())) {
            return true;
        }

        $client     = new \swoole_http_client($ip, $data['port'], $data['ssl']);
        $httpClient = $this->getContext()->getObjectPool()->get(HttpClient::class);
        $httpClient->initialization($client);
        $headers = array_merge($data['headers'], [
            'Host'        => $data['host'],
            'X-Ngx-LogId' => $this->context->getLogId(),
        ]);

        $httpClient->setHeaders($headers);
        ($data['callBack'])($httpClient);
    }

    /**
     * 设置DNS缓存
     *
     * @param $host
     * @param $ip
     */
    public static function setDnsCache($host, $ip)
    {
        self::$dnsCache[$host] = [
            $ip, time(), 1
        ];
    }

    /**
     * 获取DNS缓存
     *
     * @param $host
     * @return mixed|null
     */
    public static function getDnsCache($host)
    {
        if (!empty(self::$dnsCache[$host])) {
            if (time() - self::$dnsCache[$host][1] > 60) {
                return null;
            }

            if (self::$dnsCache[$host][2] > 10000) {
                return null;
            }

            self::$dnsCache[$host][2]++;
            return self::$dnsCache[$host][0];
        } else {
            return null;
        }
    }
}
