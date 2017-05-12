<?php
/**
 * Redis
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\DataBase\RedisAsynPool;

class Redis extends Base
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
        $this->name          = $name;
        $this->arguments     = $arguments;
        $profileName         = "redis: $name";
        $this->request       = "#redis: $name";

        $context->PGLog->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$context->logId][] = $this;
        $this->send(function ($result) use ($context, $profileName) {
            if (empty(getInstance()->coroutine->taskMap[$context->logId])) {
                return;
            }
            
            $context->PGLog->profileEnd($profileName);
            $this->result = $result;
            $this->ioBack = true;
            $this->nextRun($context->logId);
        });
    }

    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->redisAsynPool->__call($this->name, $this->arguments);
    }
}
