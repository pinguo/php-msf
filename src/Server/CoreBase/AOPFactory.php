<?php
/**
 * @desc: AOP类工厂
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/28
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\CoreBase;

use PG\MSF\Server\DataBase\CoroutineRedisHelp;

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
}
