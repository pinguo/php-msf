<?php
/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Client\Tcp;

use PG\MSF\Server\{
    CoreBase\SwooleException, Helpers\Context, Pack\IPack
};

class TcpClient
{
    /**
     * @var Context
     */
    public $context;

    /**
     * @var \swoole_client
     */
    private $client;

    private $ip;
    private $port;
    private $timeOut;

    /**
     * @var IPack
     */
    protected $pack;
    protected $package_length_type_length;

    public function __construct(\swoole_client $client, $ip, $port, $timeOut)
    {
        $this->client = $client;
        $this->ip = $ip;
        $this->port = $port;
        $this->timeOut = $timeOut * 1000;

        $this->set = get_instance()->config->get('tcpClient.set', []);
        $packTool = get_instance()->config->get('tcpClient.pack_tool', 'JsonPack');

        $this->package_length_type_length = strlen(pack($this->set['package_length_type'], 1));
        //pack class
        $pack_class_name = "\\App\\Pack\\" . $packTool;
        if (class_exists($pack_class_name)) {
            $this->pack = new $pack_class_name;
        } else {
            $pack_class_name = "\\PG\\MSF\\Server\\Pack\\" . $packTool;
            if (class_exists($pack_class_name)) {
                $this->pack = new $pack_class_name;
            } else {
                throw new SwooleException("class {$packTool} is not exist.");
            }
        }
    }


    public function coroutineSend($data)
    {
        if (!array_key_exists('path', $data)) {
            throw new SwooleException('tcp data must has path');
        }

        $path          = $data['path'];
        $data['logId'] = $this->context->PGLog->logId;
        $data = $this->encode($this->pack->pack($data));
        return new TcpClientRequestCoroutine($this, $data, $path,$this->timeOut);
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

    private function connect()
    {
        $this->client->connect($this->ip, $this->port, $this->timeOut);
    }

    private function encode($buffer)
    {
        if ($this->set['open_length_check']??0 == 1) {
            $total_length = $this->package_length_type_length + strlen($buffer) - $this->set['package_body_offset'];
            return pack($this->set['package_length_type'], $total_length) . $buffer;
        } else {
            if ($this->set['open_eof_check']??0 == 1) {
                return $buffer . $this->set['package_eof'];
            } else {
                throw new SwooleException("tcpClient won't support set");
            }
        }
    }

    private function decode($buffer)
    {
        if ($this->set['open_length_check']??0 == 1) {
            $data = substr($buffer, $this->package_length_type_length);
            return $data;
        } else {
            if ($this->set['open_eof_check']??0 == 1) {
                $data = $buffer;
                return $data;
            }
        }
    }
}