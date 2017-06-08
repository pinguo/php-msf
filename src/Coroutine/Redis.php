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

    public $phpSerialize = false;
    public $redisSerialize = false;

    public function initialization(Context $context, $redisAsynPool, $name, $arguments)
    {
        parent::init(3000);
        $this->context       = $context;
        $this->redisAsynPool = $redisAsynPool;
        $this->phpSerialize    = $redisAsynPool->phpSerialize;
        $this->redisSerialize = $redisAsynPool->redisSerialize;
        $this->name          = $name;
        $this->arguments     = $arguments;
        $this->request       = "redis.$name";
        $logId               = $context->getLogId();

        $context->getLog()->profileStart($this->request);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($result) use ($name, $logId) {
            if (empty(getInstance()->coroutine->taskMap[$logId])) {
                return;
            }

            $this->context->getLog()->profileEnd($this->request);

            if (in_array($name, ['get'])) {
                $result = $this->unSerializeHandler($result);
            } elseif (in_array($name, ['mget'])) {
                $newValues = [];
                foreach ($result as $k => $v) {
                    $newValues[$k] = $this->unSerializeHandler($v);
                }
                $result = $newValues;
            }
            
            $this->result = $result;
            $this->ioBack = true;
            $this->nextRun($logId);
        });

        return $this;
    }

    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->redisAsynPool->__call($this->name, $this->arguments);
    }

    public function destroy()
    {
        parent::destroy();
    }

    /**
     * 反序列化
     * @param $data
     * @return mixed
     */
    protected function unSerializeHandler($data)
    {
        // 如果值是null，直接返回false
        if (null === $data) {
            return false;
        }

        if (is_string($data) && $this->redisSerialize) {
            $data = $this->redisAsynPool->redisClient->_unserialize($data);
        }

        if (is_string($data) && $this->phpSerialize) {
            $data = unserialize($data);
        }

        return $data;
    }
}
