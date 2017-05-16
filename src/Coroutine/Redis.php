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
    public $context;

    public function __construct(Context $context, $redisAsynPool, $name, $arguments)
    {
        parent::__construct(3000);
        $this->context       = $context;
        $this->redisAsynPool = $redisAsynPool;
        $this->name          = $name;
        $this->arguments     = $arguments;
        $this->request       = "redis.$name";
        $logId               = $context->getLogId();

        $context->getLog()->profileStart($this->request);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($result) use ($logId) {
            if (empty(getInstance()->coroutine->taskMap[$logId])) {
                return;
            }

            $this->context->getLog()->profileEnd($this->request);
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

    public function destroy()
    {
        unset($this->context);
        unset($this->redisAsynPool);
        unset($this->name);
        unset($this->arguments);
    }
}
