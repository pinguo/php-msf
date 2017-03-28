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
            return new RedisCoroutine($arguments[0], $this->redisAsynPool, $name, array_slice($arguments, 1));
        }
    }

    /**
     * redis cache 操作封装
     * @param $key
     * @param string $value
     * @param int $expire
     * @return mixed|RedisCoroutine
     */
    public function cache($key, $value = '', $expire = 0)
    {
        if ($value === '') {
            $commandData = [$key];
            $command = 'get';
        } else {
            if (!empty($expire)) {
                $command = 'setex';
                $commandData = [$key, $expire, $value];
            } else {
                $command = 'set';
                $commandData = [$key, $value];
            }
        }

        return $this->__call($command, $commandData);
    }
}