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

class Dns extends Base
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
     * Dns constructor.
     *
     * @param Client $client
     * @param $timeout
     * @param array $headers
     */
    public function __construct(Client $client, $timeout, $headers = [])
    {
        parent::__construct($timeout);
        $logTag = $client->urlData['url'];

        $this->client    = $client;
        $this->headers   = $headers;
        $profileName     = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $logTag;
        $this->requestId = $this->getContext()->getLogId();

        getInstance()->coroutine->IOCallBack[$this->requestId][] = $this;
        $this->getContext()->getLog()->profileStart($profileName);
        $keys = array_keys(getInstance()->coroutine->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function (Client $client) use ($profileName) {
            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->coroutine->taskMap[$this->requestId])) {
                return;
            }

            $this->result       = $client;
            $this->responseTime = microtime(true);
            $this->getContext()->getLog()->profileEnd($profileName);
            $this->ioBack = true;
            $this->nextRun();
        });
    }

    /**
     * 发送DNS请求
     *
     * @param callable $callback
     */
    public function send($callback)
    {
        $this->client->asyncDNSLookup($callback, $this->headers);
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
