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

    public function __construct($redisAsynPool)
    {
        $this->redisAsynPool = $redisAsynPool;
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
        if (getInstance()->isTaskWorker()) {//如果是task进程自动转换为同步模式
            return call_user_func_array([getInstance()->getRedis(), $name], $arguments);
        } else {
            return new Redis($arguments[0], $this->redisAsynPool, $name, array_slice($arguments, 1));
        }
    }
}