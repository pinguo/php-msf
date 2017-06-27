<?php
/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Client\Tcp;

use PG\MSF\Base\Exception;
use PG\MSF\Base\Core;
use PG\MSF\Helpers\Context;
use PG\MSF\Pack\IPack;
use PG\MSF\Coroutine\TcpClientRequest;

class TcpClient extends Core
{
    /**
     * @var \swoole_client
     */
    public $client;
    /**
     * @var IPack
     */
    public $pack;

    /**
     * @var string 长度值的类型
     */
    public $packageLengthTypeLength;

    /**
     * @var string
     */
    public $ip;

    /**
     * @var int
     */
    public $port;

    /**
     * @var int
     */
    public $timeOut;

    /**
     * 初始化TcpClient
     *
     * @param \swoole_client $client
     * @param string $ip
     * @param string $port
     * @param int $timeOut
     * @throws Exception
     */
    public function initialization(\swoole_client $client, $ip, $port, $timeOut)
    {
        $this->client  = $client;
        $this->ip      = $ip;
        $this->port    = $port;
        $this->timeOut = $timeOut;

        $this->set = getInstance()->config->get('tcp_client.set', []);
        $packTool  = getInstance()->config->get('tcp_client.pack_tool', 'JsonPack');

        $this->packageLengthTypeLength = strlen(pack($this->set['package_length_type'], 1));
        $pack_class_name = "\\App\\Pack\\" . $packTool;
        if (class_exists($pack_class_name)) {
            $this->pack = new $pack_class_name;
        } else {
            $pack_class_name = "\\PG\\MSF\\Pack\\" . $packTool;
            if (class_exists($pack_class_name)) {
                $this->pack = new $pack_class_name;
            } else {
                throw new Exception("class {$packTool} is not exist.");
            }
        }
    }


    public function coroutineSend($data)
    {
        if (!array_key_exists('path', $data)) {
            throw new Exception('tcp data must has path');
        }

        $path          = $data['path'];
        $data['logId'] = $this->context->getLogId();
        $data          = $this->encode($this->pack->pack($data));
        return $this->getContext()->getObjectPool()->get(TcpClientRequest::class)->initialization($this, $data, $path, $this->timeOut);
    }

    private function encode($buffer)
    {
        if ($this->set['open_length_check']??0 == 1) {
            $total_length = $this->packageLengthTypeLength + strlen($buffer) - $this->set['package_body_offset'];
            return pack($this->set['package_length_type'], $total_length) . $buffer;
        } else {
            if ($this->set['open_eof_check']??0 == 1) {
                return $buffer . $this->set['package_eof'];
            } else {
                throw new Exception("tcp_client won't support set");
            }
        }
    }

    public function send($data, $callback)
    {
        $this->client->on('connect', function ($cli) use ($data) {
            $cli->send($data);
        });

        $this->client->on('receive', function ($cli, $recData) use ($callback) {
            $recData = $this->pack->unPack($this->decode($recData));
            if ($callback != null) {
                call_user_func($callback, $cli, $recData);
            }
        });

        $this->connect();
    }

    private function decode($buffer)
    {
        if ($this->set['open_length_check']??0 == 1) {
            $data = substr($buffer, $this->packageLengthTypeLength);
            return $data;
        } else {
            if ($this->set['open_eof_check']??0 == 1) {
                $data = $buffer;
                return $data;
            }
        }
    }

    private function connect()
    {
        $this->client->connect($this->ip, $this->port, $this->timeOut);
    }

    public function destroy()
    {
        $this->client->close();
        parent::destroy();
    }
}
