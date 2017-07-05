<?php
/**
 * 协程http客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Http\Client;
use PG\MSF\Client\Http\HttpClient;

class GetHttpClient extends Base
{
    /**
     * @var Client
     */
    public $client;

    /**
     * @var array|string
     */
    public $baseUrl;

    /**
     * @var array
     */
    public $headers;

    /**
     * 初始化获取Http Client的协程对象（异步DNS解析）
     *
     * @param Client $client
     * @param array|string $baseUrl
     * @param int $timeout
     * @param array $headers
     * @return $this|HttpClient
     */
    public function initialization(Client $client, $baseUrl, $timeout, $headers = [])
    {
        parent::init($timeout);
        if (is_array($baseUrl)) {
            $logTag = $baseUrl['scheme'] . '://' . $baseUrl['host'] . ':' . $baseUrl['port'];
        } else {
            $logTag = $baseUrl;
        }
        $this->baseUrl = $baseUrl;
        $this->client  = $client;
        $this->headers = $headers;
        $profileName   = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $logTag;
        $logId         = $this->client->context->getLogId();

        if (!is_array($this->baseUrl)) {
            $this->baseUrl = $this->parseUrl($this->baseUrl);
        }
        $ip = Client::getDnsCache($this->baseUrl['host']);

        if ($ip !== null) {
            $client     = new \swoole_http_client($ip, $this->baseUrl['port'], $this->baseUrl['ssl']);
            $httpClient = $this->getContext()->getObjectPool()->get(HttpClient::class);
            $httpClient->initialization($client);
            $headers = array_merge($headers, [
                'Host'        => $this->baseUrl['host'],
                'X-Ngx-LogId' => $this->context->getLogId(),
            ]);
            $httpClient->setHeaders($headers);
            return $httpClient;
        } else {
            getInstance()->coroutine->IOCallBack[$logId][] = $this;
            $this->client->context->getLog()->profileStart($profileName);
            $this->send(function ($httpClient) use ($profileName, $logId) {
                $this->result       = $httpClient;
                $this->responseTime = microtime(true);
                if (!empty($this->client) && !empty($this->client->context->getLog())) {
                    $this->client->context->getLog()->profileEnd($profileName);
                    $this->ioBack = true;
                    $this->nextRun($logId);
                }
            });
        }

        return $this;
    }

    /**
     * 标准化解析URL
     *
     * @param $url
     * @return bool|mixed
     */
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
     * 发送DNS请求
     *
     * @param callable $callback
     */
    public function send($callback)
    {
        $this->client->getHttpClient($this->baseUrl, $callback, $this->headers);
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
