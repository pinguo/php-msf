<?php
/**
 * CoroutineRedisHelp
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\DataBase;

use PG\MSF\Coroutine\Redis;

class CoroutineRedisHelp
{
    private $redisAsynPool;


    public $keyPrefix = '';
    public $hashKey = false;
    public $serializer = null;


    public function __construct(RedisAsynPool $redisAsynPool)
    {
        $this->redisAsynPool = $redisAsynPool;
        $this->hashKey = $redisAsynPool->hashKey;
        $this->serializer = $redisAsynPool->serializer;
        $this->keyPrefix = $redisAsynPool->keyPrefix;
    }

    /**
     * redis cache 操作封装
     *
     * @param $context
     * @param $key
     * @param string $value
     * @param int $expire
     * @return mixed|Redis
     */
    public function cache($context, $key, $value = '', $expire = 0)
    {
        if ($value === '') {
            $commandData = [$context, $key];
            $command = 'get';
        } else {
            if (!empty($expire)) {
                $command = 'setex';
                $commandData = [$context, $key, $expire, $value];
            } else {
                $command = 'set';
                $commandData = [$context, $key, $value];
            }
        }

        return $this->__call($command, $commandData);
    }

    public function __call($name, $arguments)
    {
        // key start
        if (isset($arguments[1])) {
            $key = $arguments[1];
            if (is_array($key)) {
                $isAssoc = array_keys($key) !== range(0, count($key) - 1); //true关联 false索引
                $newKey = [];
                foreach ($key as $k => $v) {
                    if ($isAssoc) {
                        $newKey[$this->generateUniqueKey($k)] = $v;
                    } else {
                        $newKey[$k] = $this->generateUniqueKey($v);
                    }
                }
            } else {
                $newKey = $this->generateUniqueKey($key);
            }
            $arguments[1] = $newKey;
        }

        if ($name == 'sDiff') {
            $arguments[2] = $this->generateUniqueKey($arguments[2]);
        }
        // key end


        // value serialize start
        if (in_array($name, ['set', 'setex'])) {
            $last = count($arguments) - 1;
            $arguments[$last] = $this->serializeHandler($arguments[$last]);
        } elseif (in_array($name, ['mset'])) {
            $keysValues = $arguments[1];
            $newValues = [];
            foreach ($keysValues as $k => $v) {
                $newValues[$k] = $this->serializeHandler($v);
            }
            $arguments[1] = $newValues;
        }
        // value serialize end

        if (getInstance()->isTaskWorker()) {//如果是task进程自动转换为同步模式
            $value = call_user_func_array([getInstance()->getRedis(), $name], $arguments);
            // return value unserialize start
            if (in_array($name, ['get'])) {
                $value = $this->unSerializeHandler($value);
            } elseif (in_array($name, ['mget'])) {
                $newValues = [];
                foreach ($value as $k => $v) {
                    $newValues[$k] = $this->unSerializeHandler($v);
                }
                $value = $newValues;
            }
            // return value unserialize end

            return $value;
        } else {
            return $arguments[0]->getObjectPool()->get(Redis::class)->initialization($arguments[0], $this->redisAsynPool, $name, array_slice($arguments, 1));
        }
    }


    protected function handleGetKey($key)
    {
        return $this->generateUniqueKey($key);
    }

    /**
     * @param string $key a key identifying a value to be cached
     * @return string a key generated from the provided key which ensures the uniqueness across applications
     */
    protected function generateUniqueKey($key)
    {
        return $this->hashKey ? md5($this->keyPrefix . $key) : $this->keyPrefix . $key;
    }

    /**
     * 序列化
     * @param $data
     * @return string
     */
    protected function serializeHandler($data)
    {
        if ($this->serializer == \Redis::SERIALIZER_PHP) {
            return serialize($data);
        } else {
            return $data;
        }
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
