<?php
/**
 * RedisCoroutine
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Server\DataBase;

use PG\MSF\Server\CoreBase\CoroutineBase;

class RedisCoroutine extends CoroutineBase
{
    /**
     * @var RedisAsynPool
     */
    public $redisAsynPool;
    public $name;
    public $arguments;

    public function __construct($redisAsynPool, $name, $arguments)
    {
        parent::__construct();
        $this->redisAsynPool = $redisAsynPool;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->request = "#redis: $name";
        $this->send(function ($result) {
            $this->result = $result;
        });
    }

    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->redisAsynPool->__call($this->name, $this->arguments);
    }
}
