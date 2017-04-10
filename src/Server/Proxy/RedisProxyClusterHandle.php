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
    /**
     * 处理入口
     * @param $redisProxy
     * @param $method
     * @param $arguments
     * @return array|bool|mixed
     */
    public static function handle($redisProxy, $method, $arguments)
    {
        $key = $arguments[1];
        //单key操作
        if (!is_array($key)) {
            return self::single($redisProxy, $method, $key, $arguments);
        } else {
            // 批量操作
            return self::multi($redisProxy, $method, $key, $arguments);
        }
    }

    /**
     * 单key
     * @param $redisProxy
     * @param $method
     * @param $key
     * @param $arguments
     * @return mixed
     */
    private static function single($redisProxy, $method, $key, $arguments)
    {
        $redisPoolName = $redisProxy->lookup($key);

        if (!isset(RedisProxy::$redisCoroutines[$redisPoolName])) {
            RedisProxy::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
        }
        $redisPoolCoroutine = RedisProxy::$redisCoroutines[$redisPoolName];

        $result = call_user_func_array([$redisPoolCoroutine, $method], $arguments);

        return $result;
    }

    /**
     * 批量处理
     * @param $redisProxy
     * @param $method
     * @param $key
     * @param $arguments
     * @return array|bool
     */
    private static function multi($redisProxy, $method, $key, $arguments)
    {
        $opArr = [];

        if (in_array(strtolower($method), ['mset', 'msetnx'])) {
            foreach ($key as $k => $v) {
                $redisPoolName = $redisProxy->lookup($k);
                $opArr[$redisPoolName][$k] = $v;
            }

            $opData = self::dispatch($opArr, $method, $arguments);

            foreach ($opData as $op) {
                $result = yield $op;
                if ($result !== 'OK') {
                    return false;
                }
            }

            return true;
        } else {
            $retData = [];
            foreach ($key as $k) {
                $redisPoolName = $redisProxy->lookup($k);
                $opArr[$redisPoolName][] = $k;
            }

            $opData = self::dispatch($opArr, $method, $arguments);

            foreach ($opData as $redisPoolName => $op) {
                $keys = $opArr[$redisPoolName];
                $values = yield $op;
                $retData = array_merge($retData, array_combine($keys, $values));
            }

            return $retData;
        }
    }

    /**
     * 分发
     * @param array $opArr
     * @param $method
     * @param $arguments
     * @return array
     */
    protected static function dispatch(array $opArr, $method, $arguments)
    {
        $opData = [];
        foreach ($opArr as $redisPoolName => $op) {
            if (!isset(RedisProxy::$redisCoroutines[$redisPoolName])) {
                RedisProxy::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
            }
            $redisPoolCoroutine = RedisProxy::$redisCoroutines[$redisPoolName];
            $opData[$redisPoolName] = call_user_func_array([$redisPoolCoroutine, $method], [$arguments[0], $op]);
        }

        return $opData;
    }
}
