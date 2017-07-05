<?php
/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Tcp\Client;

class GetTcpClient extends Base
{
    /**
     * @var Client
     */
    public $client;

    /**
     * @var string
     */
    public $baseUrl;

    /**
     * 初始化获取Tcp Client的协程对象（异步DNS解析）
     *
     * @param Client $client
     * @param string $baseUrl
     * @param int $timeout
     * @return $this
     */
    public function initialization(Client $client, $baseUrl, $timeout)
    {
        parent::init($timeout);
        $this->baseUrl = $baseUrl;
        $this->client  = $client;
        $profileName   = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->baseUrl;
        $logId         = $this->client->getContext()->getLogId();

        $this->client->getContext()->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($tcpClient, $dnsCache = false) use ($profileName, $logId) {
            $this->result = $tcpClient;
            $this->responseTime = microtime(true);
            if (!empty($this->client) && !empty($this->client->getContext()->getLog())) {
                $this->client->getContext()->getLog()->profileEnd($profileName);
                $this->ioBack = true;
                $this->nextRun($logId, $dnsCache);
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
        parent::destroy();
    }
}
