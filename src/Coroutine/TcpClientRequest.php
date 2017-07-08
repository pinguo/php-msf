<?php

/**
 * @desc: 协程Tcp客户端
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/21
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Client\Tcp\TcpClient;

class TcpClientRequest extends Base
{
    public $tcpClient;
    public $data;

    public function initialization(TcpClient $tcpClient, string $data, string $path, int $timeout)
    {
        parent::init($timeout);
        $this->tcpClient = $tcpClient;
        $this->data      = $data;
        $profileName     = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . '#api-tcp:' . $path;
        $this->requestId = $this->getContext()->getLogId();

        $this->getContext()->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->coroutine->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($cli, $recData) use ($profileName) {
            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->coroutine->taskMap[$this->requestId])) {
                return;
            }
            
            $this->result = $recData;
            $this->responseTime = microtime(true);
            $cli->close();
            $this->getContext()->getLog()->profileEnd($profileName);
            $this->ioBack = true;
            $this->nextRun();
        });

        return $this;
    }

    public function send($callback)
    {
        $this->tcpClient->send($this->data, $callback);
    }

    public function destroy()
    {
        parent::destroy();
    }
}
