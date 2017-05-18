<?php
/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright All rights reserved.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Tcp\Client;

class GetTcpClient extends Base
{
    /**
     * @var Client
     */
    public $client;
    public $baseUrl;

    public function initialization(Client $client, $baseUrl, $timeout)
    {
        parent::init($timeout);
        $this->baseUrl = $baseUrl;
        $this->client  = $client;
        $profileName   = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->baseUrl;
        $logId         = $this->client->context->getLogId();

        $this->client->context->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($tcpClient) use ($profileName, $logId) {
            $this->result = $tcpClient;
            $this->responseTime = microtime(true);
            if (!empty($this->client) && !empty($this->client->context->getLog())) {
                $this->client->context->getLog()->profileEnd($profileName);
                $this->ioBack = true;
                $this->nextRun($logId);
            }
        });

        return $this;
    }

    public function send($callback)
    {
        $this->client->getTcpClient($this->baseUrl, $callback, $this->timeout);
    }

    public function destroy()
    {
        unset($this->client);
        unset($this->baseUrl);
        parent::destroy();
    }
}
