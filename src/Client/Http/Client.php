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
     * @var array DNS查询缓存
     */
    public static $dnsCache;

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

        $ip = self::getDnsCache($urlHost);
        if ($ip !== null) {
            $this->coroutineCallBack($urlHost, $ip, $data, $headers);
        } else {
            swoole_async_dns_lookup($urlHost, function ($host, $ip) use (&$data, &$headers) {
                if ($ip === '127.0.0.0') { // fix bug
                    $ip = '127.0.0.1';
                }
                if (empty($ip)) {
                    $this->context->PGLog->warning($data['url'] . ' DNS查询失败');
                    $this->context->output->end();
                } else {
                    self::setDnsCache($host, $ip);
                    $this->coroutineCallBack($host, $ip, $data, $headers);
                }
            });
        }
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

    /**
     * DNS查询返回协程的回调
     *
     * @param $host string 主机名
     * @param $ip string 主机名对应的IP
     * @param $data array
     * @param $headers array
     * @return bool
     */
    public function coroutineCallBack($host, $ip, $data, $headers)
    {
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

    /**
     * 设置DNS缓存
     *
     * @param $host
     * @param $ip
     */
    static public function setDnsCache($host, $ip)
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
    static public function getDnsCache($host)
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