<?php
/**
 * CoroutineRedisHelp
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\DataBase;

class CoroutineRedisHelp
{
    private $redisAsynPool;

    public function __construct($redisAsynPool)
    {
        $this->redisAsynPool = $redisAsynPool;
    }

    public function __call($name, $arguments)
    {
        if (get_instance()->isTaskWorker()) {//如果是task进程自动转换为同步模式
            return call_user_func_array([get_instance()->getRedis(), $name], $arguments);
        } else {
            return new RedisCoroutine($this->redisAsynPool, $name, $arguments);
        }
    }
}