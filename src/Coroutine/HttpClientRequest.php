<?php
/**
 * http请求客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Http\HttpClient;

class HttpClientRequest extends Base
{
    /**
     * @var HttpClient
     */
    public $httpClient;

    /**
     * 发送的数据
     *
     * @var string|array
     */
    public $data;

    /**
     * 请求的 URL PATH
     *
     * @var string
     */
    public $path;

    /**
     * 请求的方法
     *
     * @var string
     */
    public $method;

    /**
     * 初始化Http异步请求协程对象
     *
     * @param HttpClient $httpClient
     * @param string $method
     * @param string $path
     * @param string|array $data
     * @param int $timeout
     * @return $this
     */
    public function initialization(HttpClient $httpClient, $method, $path, $data, $timeout)
    {
        parent::init($timeout);
        $this->httpClient = $httpClient;
        $this->path       = $path;
        $this->method     = $method;
        $this->data       = $data;
        $profileName      = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#api-http://' . $this->httpClient->headers['Host'] . $this->path;
        $logId            = $this->getContext()->getLogId();

        $this->getContext()->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($client) use ($profileName, $logId) {
            if (empty(getInstance()->coroutine->taskMap[$logId])) {
                return;
            }

            $this->result       = (array)$client;
            $this->responseTime = microtime(true);
            $this->getContext()->getLog()->profileEnd($profileName);
            $this->ioBack = true;
            $this->nextRun($logId);
        });

        return $this;
    }

    /**
     * 发送异步的Http请求
     *
     * @param callable $callback
     */
    public function send($callback)
    {
        switch ($this->method) {
            case 'POST':
                $this->httpClient->post($this->path, $this->data, $callback);
                break;
            case 'GET':
                $this->httpClient->get($this->path, $this->data, $callback);
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
