<?php
/**
 * Redis
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\DataBase\RedisAsynPool;
use PG\MSF\Helpers\Context;

class Redis extends Base
{
    /**
     * @var RedisAsynPool
     */
    public $redisAsynPool;
    public $name;
    public $arguments;

    public function __construct(Context $context, $redisAsynPool, $name, $arguments)
    {
        parent::__construct(3000);
        $this->redisAsynPool = $redisAsynPool;
        $this->name          = $name;
        $this->arguments     = $arguments;
        $profileName         = "redis.$name";
        $this->request       = "redis.$name";

        $context->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$context->getLogId()][] = $this;
        $this->send(function ($result) use ($context, $profileName) {
            if (empty(getInstance()->coroutine->taskMap[$context->getLogId()])) {
                return;
            }

            $context->getLog()->profileEnd($profileName);
            $this->result = $result;
            $this->ioBack = true;
            $this->nextRun($context->getLogId());
        });
    }

    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->redisAsynPool->__call($this->name, $this->arguments);
    }

    public function destroy()
    {
        unset($this->redisAsynPool);
        unset($this->name);
        unset($this->arguments);
    }
}
