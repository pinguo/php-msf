<?php
/**
 * @desc: AOP类工厂
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/28
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\MSF\DataBase\CoroutineRedisProxy;
use PG\MSF\Memory\Pool;
use PG\MSF\Proxy\IProxy;
use PG\AOP\Factory;
use PG\AOP\Wrapper;

class AOPFactory extends Factory
{
    /**
     * 通过反射获取类的public属性默认值（销毁对象）
     *
     * @var array
     */
    protected static $reflections = [];

    /**
     * 获取协程redis
     *
     * @param CoroutineRedisProxy $redisPoolCoroutine
     * @param Core $coreBase
     * @return Wrapper |CoroutineRedisProxy
     */
    public static function getRedisPoolCoroutine(CoroutineRedisProxy $redisPoolCoroutine, $coreBase)
    {
        $AOPRedisPoolCoroutine = new Wrapper($redisPoolCoroutine);
        $AOPRedisPoolCoroutine->registerOnBefore(function ($method, $arguments) use ($coreBase) {
            $context = $coreBase->getContext();
            array_unshift($arguments, $context);
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            return $data;
        });
        return $AOPRedisPoolCoroutine;
    }

    /**
     * 获取redis proxy
     *
     * @param $redisProxy
     * @param Core $coreBase
     * @return Wrapper|\Redis
     */
    public static function getRedisProxy(IProxy $redisProxy, $coreBase)
    {
        $redis = new Wrapper($redisProxy);
        $redis->registerOnBefore(function ($method, $arguments) use ($redisProxy, $coreBase) {
            $context = $coreBase->getContext();
            array_unshift($arguments, $context);
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            $data['result'] = $redisProxy->handle($method, $arguments);
            return $data;
        });

        return $redis;
    }

    /**
     * 获取对象池实例
     *
     * @param Pool $pool
     * @param Core $coreBase
     * @return Wrapper|Pool
     */
    public static function getObjectPool(Pool $pool, $coreBase)
    {
        $AOPPool = new Wrapper($pool);

        $AOPPool->registerOnBefore(function ($method, $arguments) use ($coreBase) {
            if ($method === 'push') {
                // 手工处理释放资源
                method_exists($arguments[0], 'destroy') && $arguments[0]->destroy();
                // 自动处理释放资源
                $class = get_class($arguments[0]);
                if (!empty(self::$reflections[$class]) && method_exists($arguments[0], 'resetProperties')) {
                    $arguments[0]->resetProperties(self::$reflections[$class]);
                }
            }
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            return $data;
        });

        $AOPPool->registerOnAfter(function ($method, $arguments, $result) use ($coreBase) {
            //取得对象后放入请求内部bucket
            if ($method === 'get' && is_object($result)) {
                //使用次数+1
                $result->useCount++;
                $coreBase->objectPoolBuckets[] = $result;
                $result->context = &$coreBase->context;
                $result->parent  = &$coreBase;
                $class = get_class($result);
                if (!isset(self::$reflections[$class])) {
                    $reflection  = new \ReflectionClass($class);
                    $default     = $reflection->getDefaultProperties();
                    $ps          = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
                    $ss          = $reflection->getProperties(\ReflectionProperty::IS_STATIC);
                    $autoDestroy = [];
                    foreach ($ps as $val) {
                        $autoDestroy[$val->getName()] = $default[$val->getName()];
                    }
                    foreach ($ss as $val) {
                        unset($autoDestroy[$val->getName()]);
                    }
                    unset($autoDestroy['useCount']);
                    unset($autoDestroy['genTime']);
                    unset($autoDestroy['coreName']);
                    self::$reflections[$class] = $autoDestroy;
                }
                unset($reflection);
                unset($default);
                unset($ps);
                unset($ss);
            }
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            $data['result'] = $result;
            return $data;
        });

        return $AOPPool;
    }
}
