<?php
/**
 * 协程http客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Http\Client;

class GetHttpClient extends Base
{
    /**
     * @var Client
     */
    public $client;
    public $baseUrl;
    public $headers;

    public function initialization(Client $client, $baseUrl, $timeout, $headers = [])
    {
        parent::init($timeout);
        if (is_array($baseUrl)) {
            $this->baseUrl = $baseUrl['scheme'] . '://' . $baseUrl['host'] . ':' . $baseUrl['port'];
        } else {
            $this->baseUrl = $baseUrl;
        }
        $this->client  = $client;
        $this->headers = $headers;
        $profileName   = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->baseUrl;
        $logId         = $this->client->context->getLogId();

        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->client->context->getLog()->profileStart($profileName);
        $this->send(function ($httpClient, $dnsCache = false) use ($profileName, $logId) {
            $this->result = $httpClient;
            $this->responseTime = microtime(true);
            if (!empty($this->client) && !empty($this->client->context->getLog())) {
                $this->client->context->getLog()->profileEnd($profileName);
                $this->ioBack = true;
                $this->nextRun($logId, $dnsCache);
            }
        });

        return $this;
    }

    public function send($callback)
    {
        $this->client->getHttpClient($this->baseUrl, $callback, $this->headers);
    }

    public function destroy()
    {
        parent::destroy();
    }
}
