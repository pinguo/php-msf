<?php
/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright All rights reserved.
 */

namespace PG\MSF\Client\Tcp;

use PG\MSF\{
    Base\SwooleException, Helpers\Context
};

class Client
{
    /**
     * 上下文
     * @var Context
     */
    public $context;

    /**
     * @var IPack
     */
    protected $pack;
    protected $set;

    public function __construct()
    {
        $this->context = new Context();
        $this->set = getInstance()->config->get('tcpClient.set', []);
    }

    /**
     * 获取一个tcp客户端
     * @param $baseUrl
     * @param $callBack
     * @param $timeOut
     * @throws SwooleException
     */
    public function getTcpClient($baseUrl, $callBack, $timeOut)
    {
        $data = [];
        $parseBaseUrlResult = explode(":", $baseUrl);

        if (count($parseBaseUrlResult) != 2) {
            throw new SwooleException($baseUrl . ' must be an ip:port string');
        }

        $data['host'] = $parseBaseUrlResult[0];
        $data['port'] = $parseBaseUrlResult[1];
        $data['callBack'] = $callBack;

        swoole_async_dns_lookup($data['host'], function ($host, $ip) use (&$data, $timeOut) {
            if (empty($ip)) {
                $this->context->PGLog->warning($data['url'] . ' DNS查询失败');
                $this->context->output->outputJson([], 'error', 500);
            } else {
                if (empty($this->context)) {
                    return true;
                }
                
                $c = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
                $c->set($this->set);

                $c->on('error', function ($cli) use ($data) {
                    throw new SwooleException($data['host'] . ':' . $data['port'] . ' Connect failed');
                });

                $c->on('close', function ($cli) {
                });

                $tcpClient = new TcpClient($c, $ip, $data['port'], $timeOut / 1000);
                $tcpClient->context = $this->context;
                call_user_func($data['callBack'], $tcpClient);
            }
        });
    }

    /**
     * 协程方式获取 TcpClient
     * @param $baseUrl 127.0.0.1:8000
     * @param int $timeout
     * @return GetTcpClientCoroutine
     */
    public function coroutineGetTcpClient($baseUrl, $timeout = 30000)
    {
        return new GetTcpClientCoroutine($this, $baseUrl, $timeout);
    }
}