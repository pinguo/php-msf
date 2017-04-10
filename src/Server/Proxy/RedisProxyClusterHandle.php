<?php
/**
 * @desc: RedisProxyClusterHandle
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/10
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Proxy;


class RedisProxyClusterHandle
{
    public static function handle($redisProxy, $method, $arguments)
    {
        $key = $arguments[1];
        //单key操作
        if (!is_array($key)) {
            return self::simple($redisProxy, $method, $key, $arguments);
        } else {
            // 批量操作
        }
    }


    private static function simple($redisProxy, $method, $key, $arguments)
    {
        $redisPoolName = $redisProxy->lookup($key);

        if (!isset(RedisProxy::$redisCoroutines[$redisPoolName])) {
            RedisProxy::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
        }
        $redisPoolCoroutine = RedisProxy::$redisCoroutines[$redisPoolName];

        $result = call_user_func_array([$redisPoolCoroutine, $method], $arguments);

        return $result;
    }
}
