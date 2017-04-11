<?php
/**
 * @desc: AOP类工厂
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/28
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\CoreBase;

use Flexihash\Flexihash;
use PG\MSF\Server\DataBase\CoroutineRedisHelp;
use PG\MSF\Server\Memory\Pool;
use PG\MSF\Server\Proxy\RedisProxyClusterHandle;
use PG\MSF\Server\Proxy\RedisProxyMasterSlaveHandle;

class AOPFactory
{
    /**
     * 获取协程redis
     * @param CoroutineRedisHelp $redisPoolCoroutine
     * @param CoreBase $coreBase
     * @return AOP|CoroutineRedisHelp
     */
    public static function getRedisPoolCoroutine(CoroutineRedisHelp $redisPoolCoroutine, CoreBase $coreBase)
    {
        $redisPoolCoroutine = new AOP($redisPoolCoroutine);
        $redisPoolCoroutine->registerOnBefore(function ($method, $arguments) use ($coreBase) {
            $context = $coreBase->getContext();
            array_unshift($arguments, $context);
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            return $data;
        });
        return $redisPoolCoroutine;
    }

    /**
     * 获取redis proxy
     * @param $redisProxy
     * @param CoreBase $coreBase
     * @return AOP
     */
    public static function getRedisProxy($redisProxy, CoreBase $coreBase)
    {
        $redis = new AOP($redisProxy);
        $redis->registerOnBefore(function ($method, $arguments) use ($redisProxy, $coreBase) {
            $context = $coreBase->getContext();
            array_unshift($arguments, $context);
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            //分布式模式
            if ($redisProxy instanceof Flexihash) {
                $data['result'] = RedisProxyClusterHandle::handle($redisProxy, $method, $arguments);
            } else {
                //主从模式
                $data['result'] = RedisProxyMasterSlaveHandle::handle($redisProxy, $method, $arguments);
            }

            return $data;
        });

        return $redis;
    }

    /**
     * 获取对象池实例
     * @param Pool $pool
     * @param CoreBase $coreBase
     * @return AOP|Pool
     */
    public static function getObjectPool(Pool $pool, CoreBase $coreBase)
    {
        $pool = new AOP($pool);

        $pool->registerOnBefore(function ($method, $arguments) use ($coreBase) {
            if ($method === 'push') {
                //判断是否还返还对象：使用时间超过2小时或者使用次数大于10000则不返还，直接销毁
                if (($arguments[0]->genTime + 7200) < time() || $arguments[0]->useCount > 10000) {
                    $data['result'] = false;
                    unset($arguments[0]);
                } else {
                    //返还时调用destroy方法
                    method_exists($arguments[0], 'destroy') && ($arguments[0])->destroy();
                }
            }
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            return $data;
        });

        $pool->registerOnAfter(function ($method, $arguments, $result) use ($coreBase) {
            //取得对象后放入请求内部bucket
            if ($method === 'get' && is_object($result)) {
                //使用次数+1
                $result->useCount++;
                //获得对象后调用initialization方法
                method_exists($result, 'initialization') && $result->initialization();
                $coreBase->objectPoolBuckets[] = $result;
            }
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            $data['result'] = $result;
            return $data;
        });

        return $pool;
    }
}
