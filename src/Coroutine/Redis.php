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

    public $keyPrefix = '';
    public $hashKey = false;
    public $phpSerialize = false;
    public $redisSerialize = false;

    public function initialization(Context $context, $redisAsynPool, $name, $arguments)
    {
        parent::init(3000);
        $this->context = $context;

        $this->redisAsynPool = $redisAsynPool;
        $this->hashKey = $redisAsynPool->hashKey;
        $this->phpSerialize = $redisAsynPool->phpSerialize;
        $this->keyPrefix = $redisAsynPool->keyPrefix;
        $this->redisSerialize = $redisAsynPool->redisSerialize;

        $this->name = $name;
        $this->arguments = $arguments;
        $this->request = "redis.$name";
        $logId = $context->getLogId();

        $context->getLog()->profileStart($this->request);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($result) use ($name, $logId) {
            if (empty(getInstance()->coroutine->taskMap[$logId])) {
                return;
            }

            $this->context->getLog()->profileEnd($this->request);

            switch ($name) {
                case 'get':
                    $result = $this->unSerializeHandler($result);
                    break;
                case 'mget';
                    $newValues = [];
                    $keys = $this->arguments[0];
                    $len = strlen($this->keyPrefix);
                    foreach ($result as $k => $v) {
                        $key = substr($keys[$k], $len);
                        $newValues[$key] = $this->unSerializeHandler($v);
                    }
                    $result = $newValues;
                    break;
                case 'eval':
                    //如果redis中的数据本身没有进行序列化，同时返回值是json，那么解析成array
                    $decodeVal = @json_decode($result, true);
                    if (is_array($decodeVal)) {
                        $result = $decodeVal;
                    }

                    if (is_array($result)) {
                        //处理反序列化
                        foreach ($result as $k => $v) {
                            $result[$k] = $this->unSerializeHandler($v);
                        }
                    } else {
                        $result = $this->unSerializeHandler($result);
                    }
                    break;
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

        try {
            if (is_string($data) && $this->redisSerialize) {
                $data = $this->redisAsynPool->redisClient->_unserialize($data);
            }

            if (is_string($data) && $this->phpSerialize) {
                $data = unserialize($data);
            }
        } catch (\Exception $exception) {
            // do noting
        }

        return $data;
    }
}
