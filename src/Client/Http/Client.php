<?php
/**
 * http客户端,支持协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Client\Http;

use PG\MSF\Base\Exception;
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
     * @param string | array $baseUrl
     * @param $callBack
     * @throws \PG\MSF\Base\Exception
     */
    public function getHttpClient($baseUrl, $callBack, array $headers = [])
    {
        $data             = [];
        $data['callBack'] = $callBack;
        if (is_array($baseUrl)) {
            $data['url']      = $baseUrl['scheme'] . '://' . $baseUrl['host'] . ':' . $baseUrl['port'];
            $data['port']     = $baseUrl['port'];
            $urlHost          = $baseUrl['host'];
            if ($baseUrl['scheme'] == 'https') {
                $data['ssl'] = true;
            }
        } else {
            $data['url'] = $baseUrl;
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
        }

        $ip = self::getDnsCache($urlHost);
        if ($ip !== null) {
            $this->coroutineCallBack($urlHost, $ip, $data, $headers, true);
        } else {
            $logId = $this->getContext()->getLogId();
            swoole_async_dns_lookup($urlHost, function ($host, $ip) use (&$data, &$headers, $logId) {
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
                    $this->coroutineCallBack($host, $ip, $data, $headers);
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

        if (empty($parseUrlResult['port'])) {
            if ($parseUrlResult['scheme'] == 'http') {
                $parseUrlResult['port'] = 80;
            } else {
                $parseUrlResult['port'] = 443;
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

        $sendDnsQuery = $this->getContext()->getObjectPool()->get(GetHttpClient::class)->initialization($this, $parseUrlResult, 300, $headers);
        /**
         * @var $httpClient HttpClient
         */
        $httpClient   = yield $sendDnsQuery;
        $sendPostReq  = $httpClient->coroutinePost($parseUrlResult['path'] . $parseUrlResult['query'], $data, $timeout);
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

        if (empty($parseUrlResult['port'])) {
            if ($parseUrlResult['scheme'] == 'http') {
                $parseUrlResult['port'] = 80;
            } else {
                $parseUrlResult['port'] = 443;
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

        $sendDnsQuery = $this->getContext()->getObjectPool()->get(GetHttpClient::class)->initialization($this, $parseUrlResult, 300, $headers);
        /**
         * @var $httpClient HttpClient
         */
        $httpClient   = yield $sendDnsQuery;
        $sendPostReq  = $httpClient->coroutineGet($parseUrlResult['path'] . $parseUrlResult['query'], $query, $timeout);
        $result       = yield $sendPostReq;

        return $result;
    }

    /**
     * DNS查询返回协程的回调
     *
     * @param $host string 主机名
     * @param $ip string 主机名对应的IP
     * @param $data array
     * @param $headers array
     * @param $dnsCache boolean
     * @return bool
     */
    public function coroutineCallBack($host, $ip, $data, $headers, $dnsCache = false)
    {
        if (empty($this->context) || empty($this->context->getLog())) {
            return true;
        }

        $client     = new \swoole_http_client($ip, $data['port'], $data['ssl']);
        $httpClient = $this->getContext()->getObjectPool()->get(HttpClient::class);
        $httpClient->initialization($client);
        $headers = array_merge($headers, [
            'Host' => $host,
            'X-Ngx-LogId' => $this->context->getLogId(),
        ]);

        $httpClient->setHeaders($headers);
        call_user_func($data['callBack'], $httpClient, $dnsCache);
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
