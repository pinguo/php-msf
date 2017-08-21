<?php
/**
 * DNS查询协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Http\Client;

class Dns extends Base
{
    /**
     * @var Client HTTP客户端实例
     */
    public $client;

    /**
     * @var array 请求的额外HTTP报头
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

        $this->client    = $client;
        $this->headers   = $headers;
        $profileName     = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->client->urlData['host'];
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
     * 发送DNS查询请求
     *
     * @param callable $callback
     * @return $this
     */
    public function send($callback)
    {
        $this->client->asyncDNSLookup($callback, $this->headers);
        return $this;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
