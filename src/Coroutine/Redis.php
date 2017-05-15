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

    public $serializer = null;

    public function __construct(Context $context, $redisAsynPool, $name, $arguments)
    {
        parent::__construct(3000);
        $this->redisAsynPool = $redisAsynPool;
        $this->serializer = $redisAsynPool->serializer;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->request = "redis.$name";

        $context->getLog()->profileStart($this->request);
        getInstance()->coroutine->IOCallBack[$context->getLogId()][] = $this;
        $this->send(function ($result) use ($name, $context) {
            if (empty(getInstance()->coroutine->taskMap[$context->getLogId()])) {
                return;
            }

            $context->getLog()->profileEnd($this->request);

            // return value unserialize start
            if (in_array($name, ['get'])) {
                $result = $this->unSerializeHandler($result);
            } elseif (in_array($name, ['mget'])) {
                $newValues = [];
                foreach ($result as $k => $v) {
                    $newValues[$k] = $this->unSerializeHandler($v);
                }
                $result = $newValues;
            }
            // return value unserialize end

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

    /**
     * 反序列化
     * @param $data
     * @return mixed
     */
    protected function unSerializeHandler($data)
    {
        if ($this->serializer == \Redis::SERIALIZER_PHP) {
            return unserialize($data);
        } else {
            return $data;
        }
    }
}
