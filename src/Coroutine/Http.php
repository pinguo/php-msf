<?php
/**
 * HTTP请求协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Http\Client;

/**
 * Class Http
 * @package PG\MSF\Coroutine
 */
class Http extends Base
{
    /**
     * @var Client HTTP客户端实例
     */
    public $client;

    /**
     * @var string|array 发送的数据
     */
    public $data;

    /**
     * @var string 请求的URL PATH
     */
    public $path;

    /**
     * @var string 请求的方法
     */
    public $method;

    /**
     * 初始化Http异步请求协程对象
     *
     * @param Client $client Client实例
     * @param string $method HTTP请求的方法
     * @param string $path HTTP请求的URL Path
     * @param string|array $data 发送的数据
     * @param int $timeout
     */
    public function __construct(Client $client, $method, $path, $data, $timeout)
    {
        parent::__construct($timeout);
        $this->client     = $client;
        $this->path       = $path;
        $this->method     = $method;
        $this->data       = $data;
        $profileName      = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#api-http://' . $this->client->urlData['host'] . ':' . $this->client->urlData['port'] . $this->path;
        $this->requestId  = $this->getContext()->getRequestId();
        $requestId        = $this->requestId;

        $this->getContext()->getLog()->profileStart($profileName);
        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($client) use ($profileName, $requestId) {
            if (empty($this->getContext()) || ($requestId != $this->getContext()->getRequestId())) {
                return;
            }

            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
                return;
            }

            // 发现拒绝建立连接，删除DNS缓存
            if (is_object($client) && ($client->errCode == 111 || $client->statusCode == 404)) {
                Client::clearDnsCache($this->client->urlData['host']);
                $client->isClose = true;
            }

            if (is_object($client) && $client->errCode != 0) {
                $this->getContext()->getLog()->warning(dump($client, false, true));
                $client->isClose = true;
            }

            $client->ioBack     = true;
            $this->result       = (array)$client;
            $this->responseTime = microtime(true);
            $this->getContext()->getLog()->profileEnd($profileName);
            $this->ioBack = true;
            $this->nextRun();
        });
    }

    /**
     * 发送异步的HTTP请求
     *
     * @param callable $callback 请求响应的回调函数
     */
    public function send($callback)
    {
        switch ($this->method) {
            case 'POST':
                $this->client->post($this->path, $this->data, $callback);
                break;
            case 'GET':
                $this->client->get($this->path, $this->data, $callback);
                break;
            case 'EXECUTE':
                if (!empty($this->data)) {
                    $this->client->setData($this->data);
                }
                $this->client->execute($this->path, $callback);
                break;
        }
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
