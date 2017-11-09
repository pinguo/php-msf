<?php
/**
 * DNS查询协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Http\Client;

/**
 * Class Dns
 * @package PG\MSF\Coroutine
 */
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
     * @param Client $client Client实例
     * @param int $timeout DNS解析超时时间，单位毫秒
     * @param array $headers HTTP请求的报头列表
     */
    public function __construct(Client $client, $timeout, $headers = [])
    {
        parent::__construct($timeout);

        $this->client    = $client;
        $this->headers   = $headers;
        $profileName     = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#dns-' . $this->client->urlData['host'];
        $this->requestId = $this->getContext()->getRequestId();
        $requestId       = $this->requestId;

        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $this->getContext()->getLog()->profileStart($profileName);
        $keys = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function (Client $client) use ($profileName, $requestId) {
            if (empty($this->getContext()) || ($requestId != $this->getContext()->getRequestId())) {
                return;
            }

            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
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
     * @param callable $callback DNS解析完成后的回调
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
