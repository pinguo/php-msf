<?php
/**
 * RedisCoroutine
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\DataBase;

use PG\MSF\Server\Coroutine\CoroutineBase;

class RedisCoroutine extends CoroutineBase
{
    /**
     * @var RedisAsynPool
     */
    public $redisAsynPool;
    public $name;
    public $arguments;

    public function __construct($context, $redisAsynPool, $name, $arguments)
    {
        parent::__construct(3000);
        $this->redisAsynPool = $redisAsynPool;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->request = "#redis: $name";
        $logId = $context->PGLog->logId;
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($result) use ($logId) {
            $this->result = $result;
            $this->ioBack = true;
            $this->nextRun($logId);
        });
    }

    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->redisAsynPool->__call($this->name, $this->arguments);
    }
}
