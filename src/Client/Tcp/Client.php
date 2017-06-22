<?php
/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright All rights reserved.
 */

namespace PG\MSF\Client\Tcp;

use PG\MSF\Base\Exception;
use PG\MSF\Base\Core;
use PG\MSF\Helpers\Context;
use PG\MSF\Coroutine\GetTcpClient;

class Client extends Core
{
    /**
     * @var array DNS查询缓存
     */
    public static $dnsCache;

    /**
     * 获取一个tcp客户端
     * @param $baseUrl
     * @param $callBack
     * @param $timeOut
     * @throws Exception
     */
    public function getTcpClient($baseUrl, $callBack, $timeOut)
    {
        $data               = [];
        $parseBaseUrlResult = explode(":", $baseUrl);

        if (count($parseBaseUrlResult) != 2) {
            throw new Exception($baseUrl . ' must be an ip:port string');
        }

        $data['host']     = $parseBaseUrlResult[0];
        $data['port']     = $parseBaseUrlResult[1];
        $data['callBack'] = $callBack;

        $ip = self::getDnsCache($data['host']);
        if ($ip !== null) {
            $this->coroutineCallBack($data['host'], $ip, $data, $timeOut);
        } else {
            $logId = $this->getContext()->getLogId();
            swoole_async_dns_lookup($data['host'], function ($host, $ip) use (&$data, $timeOut, $logId) {
                if (empty(getInstance()->coroutine->taskMap[$logId])) {
                    return;
                }

                if (empty($ip)) {
                    $this->context->getLog()->warning($data['url'] . ' DNS查询失败');
                } else {
                    self::setDnsCache($host, $ip);
                    $this->coroutineCallBack($host, $ip, $data, $timeOut);
                }
            });
        }
    }

    /**
     * 协程方式获取 TcpClient
     * @param $baseUrl 127.0.0.1:8000
     * @param int $timeout
     * @return GetTcpClient
     */
    public function coroutineGetTcpClient($baseUrl, $timeout = 30000)
    {
        return $this->getContext()->getObjectPool()->get(GetTcpClient::class)->initialization($this, $baseUrl, $timeout);
    }

    /**
     * DNS查询返回协程的回调
     *
     * @param $host string 主机名
     * @param $ip string 主机名对应的IP
     * @param $data array
     * @param $headers array
     * @return bool
     */
    public function coroutineCallBack($host, $ip, $data, $timeOut)
    {
        if (empty($this->context)) {
            return true;
        }

        $c = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $c->set(getInstance()->config->get('tcp_client.set', []));

        $c->on('error', function ($cli) use ($data) {
            throw new Exception($data['host'] . ':' . $data['port'] . ' Connect failed');
        });

        $c->on('close', function ($cli) {
        });

        $tcpClient = $this->getContext()->getObjectPool()->get(TcpClient::class);
        $tcpClient->initialization($c, $ip, $data['port'], $timeOut / 1000);
        call_user_func($data['callBack'], $tcpClient);
    }

    /**
     * 设置DNS缓存
     *
     * @param $host
     * @param $ip
     */
    public static function setDnsCache($host, $ip)
    {
        self::$dnsCache[$host] = [
            $ip, time(), 1
        ];
    }

    /**
     * 获取DNS缓存
     *
     * @param $host
     * @return mixed|null
     */
    public static function getDnsCache($host)
    {
        if (!empty(self::$dnsCache[$host])) {
            if (time() - self::$dnsCache[$host][1] > 60) {
                return null;
            }

            if (self::$dnsCache[$host][2] > 10000) {
                return null;
            }

            self::$dnsCache[$host][2]++;
            return self::$dnsCache[$host][0];
        } else {
            return null;
        }
    }
}
