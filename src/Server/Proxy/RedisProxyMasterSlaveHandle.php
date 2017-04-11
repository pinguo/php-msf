<?php
/**
 * @desc: RedisProxyMasterSlaveHandle
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/11
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Proxy;


class RedisProxyMasterSlaveHandle implements IProxyHandle
{
    /**
     * 处理入口
     * @param $redisProxy
     * @param $method
     * @param $arguments
     * @return array|bool|mixed
     */
    public static function handle($redisProxy, $method, $arguments)
    {
        //读
        $lowerMethod = strtolower($method);
        if (strpos($lowerMethod, 'get') !== false ||
            strpos($lowerMethod, 'exists') !== false ||
            strpos($lowerMethod, 'range') !== false ||
            strpos($lowerMethod, 'count') !== false ||
            strpos($lowerMethod, 'size') !== false
        ) {
            $slaves = $redisProxy->get('slaves');
            $rand = array_rand($slaves);
            $redisPoolName = $slaves[$rand];
        } else {
            //写
            $redisPoolName = $redisProxy->get('master');
        }

        if (!isset(RedisProxy::$redisCoroutines[$redisPoolName])) {
            RedisProxy::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
        }
        $redisPoolCoroutine = RedisProxy::$redisCoroutines[$redisPoolName];

        return call_user_func_array([$redisPoolCoroutine, $method], $arguments);
    }
}
