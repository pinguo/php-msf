<?php
/**
 * AOP类工厂
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\AOP\Factory;
use PG\AOP\Wrapper;
use PG\MSF\DataBase\CoroutineRedisProxy;
use PG\MSF\Memory\Pool;
use PG\MSF\Proxy\IProxy;

class AOPFactory extends Factory
{
    /**
     * 通过反射获取类的public属性默认值（销毁对象）
     *
     * @var array
     */
    protected static $reflections = [];

    /**
     * 所有为Task类的标识
     * @var array
     */
    protected static $taskClasses = [];

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
                } else {
                    if (!empty(self::$reflections[$class])) {
                        foreach (self::$reflections[$class] as $prop => $val) {
                            $arguments[0]->{$prop} = $val;
                        }
                    }
                }
                $arguments[0]->__isContruct = false;
            }

            if ($method === 'get') {
                $className = $arguments[0];
                // 支持TaskProxy
                do {
                    if (isset(self::$taskClasses[$className])) {
                        break;
                    }

                    $parents = class_parents($className, true);
                    if (empty($parents)) {
                        self::$taskClasses[$className] = 0;
                        break;
                    }

                    $flag = false;
                    foreach ($parents as $parentClassName) {
                        if ($parentClassName == 'PG\MSF\Tasks\Task') {
                            self::$taskClasses[$className] = 1;
                            $flag = true;
                            break;
                        }
                    }

                    if ($flag) {
                        break;
                    }

                    self::$taskClasses[$className] = 0;
                } while (0);

                if (self::$taskClasses[$className]) {
                    // worker进程
                    if (!empty(getInstance()->server)
                        && property_exists(getInstance()->server, 'taskworker')
                        && !getInstance()->server->taskworker) {
                        $arguments[0] = '\PG\MSF\Tasks\TaskProxy';
                        array_push($arguments, $className);
                    }
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
                $result->__useCount++;
                $coreBase->objectPoolBuckets[] = $result;
                $result->context = &$coreBase->context;
                if (!empty($result->context)) {
                    $result->context->setOwner($result);
                }
                $result->parent = &$coreBase;
                $class = get_class($result);
                // 支持TaskProxy
                if ($result instanceof \PG\MSF\Tasks\TaskProxy) {
                    $result->taskName = array_pop($arguments);
                }
                // 自动调用构造方法
                if (method_exists($result, '__construct') && $result->__isContruct == false) {
                    $result->__isContruct = true;
                    $result->__construct(...array_slice($arguments, 1));
                }
                // 支持自动销毁public成员变量
                if (!isset(self::$reflections[$class])) {
                    $reflection = new \ReflectionClass($class);
                    $default = $reflection->getDefaultProperties();
                    $ps = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
                    $ss = $reflection->getProperties(\ReflectionProperty::IS_STATIC);
                    $autoDestroy = [];
                    foreach ($ps as $val) {
                        $autoDestroy[$val->getName()] = $default[$val->getName()];
                    }
                    foreach ($ss as $val) {
                        unset($autoDestroy[$val->getName()]);
                    }
                    unset($autoDestroy['__useCount']);
                    unset($autoDestroy['__genTime']);
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
