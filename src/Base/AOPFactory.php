<?php
/**
 * AOP类工厂
 *
 * 基于AOP完善的支持请求上下文，Redis连接池及代理，MySQL连接池等
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\MSF\Macro;
use PG\AOP\MI;
use PG\AOP\Factory;
use PG\AOP\Wrapper;
use PG\MSF\Proxy\IProxy;
use PG\MSF\Proxy\RedisProxyCluster;
use PG\MSF\Proxy\RedisProxyMasterSlave;
use PG\MSF\Proxy\MysqlProxyMasterSlave;
use PG\MSF\Pools\MysqlAsynPool;
use PG\MSF\Pools\CoroutineRedisProxy;
use PG\MSF\Tasks\Task;
use PG\MSF\Tasks\TaskProxy;

/**
 * Class AOPFactory
 * @package PG\MSF\Base
 */
class AOPFactory extends Factory
{
    /**
     * @var array 通过反射获取类的public属性默认值（销毁对象）
     */
    protected static $reflections = [];

    /**
     * @var array 所有为Task类的标识
     */
    protected static $taskClasses = [];


    /**
     * 获取Mysql/Redis等数据库代理.
     *
     * @param IProxy $proxy
     * @param Core   $coreBase
     *
     * @return Wrapper
     */
    protected static function getDBProxy(IProxy $proxy, $coreBase)
    {
        $proxyWrapper = new Wrapper($proxy);

        $proxyWrapper->registerOnBefore(function (string $method, array $arguments) use ($proxy, $coreBase) {
            $context = $coreBase->getContext();
            array_unshift($arguments, $context);
            return [
                'method' => $method,
                'arguments' => $arguments,
                'result' => $proxy->handle($method, $arguments),
            ];
        });

        return $proxyWrapper;
    }

    /**
     * 获取协程redis
     *
     * @param CoroutineRedisProxy $redisPoolCoroutine Redis协程辅助类实例
     * @param Core $coreBase Core实例（通常为Controller实例）
     *
     * @return Wrapper|CoroutineRedisProxy AOP包装的CoroutineRedisProxy切片实例
     */
    public static function getRedisPoolCoroutine(CoroutineRedisProxy $redisPoolCoroutine, $coreBase)
    {
        $aopRedisPoolCoroutine = new Wrapper($redisPoolCoroutine);
        $aopRedisPoolCoroutine->registerOnBefore(function ($method, $arguments) use ($coreBase) {
            $context = $coreBase->getContext();
            array_unshift($arguments, $context);
            $data = [
                'method' => $method,
                'arguments' => $arguments,
            ];
            return $data;
        });
        return $aopRedisPoolCoroutine;
    }

    /**
     * 获取协程mysql
     *
     * @param MysqlAsynPool $mysqlPoolCoroutine MySQL连接池实例
     * @param Core $coreBase Core实例（通常为Controller实例）
     *
     * @return Wrapper|MysqlAsynPool AOP包装的MysqlAsynPool切片实例
     */
    public static function getMysqlPoolCoroutine(MysqlAsynPool $mysqlPoolCoroutine, $coreBase)
    {
        $aopMysqlPoolCoroutine = new Wrapper($mysqlPoolCoroutine);
        $aopMysqlPoolCoroutine->registerOnBefore(function ($method, $arguments) use ($coreBase) {
            $context = $coreBase->getContext();
            $arguments[] = $context;
            $data = [
                'method' => $method,
                'arguments' => $arguments,
            ];
            return $data;
        });
        return $aopMysqlPoolCoroutine;
    }

    /**
     * 获取mysql proxy
     *
     * @param IProxy|MysqlProxyMasterSlave $mysqlProxy MysqlProxy实例
     * @param Core $coreBase Core实例（通常为Controller实例）
     * @return Wrapper|MysqlAsynPool AOP包装的MysqlProxyMasterSlave切片实例
     */
    public static function getMysqlProxy(IProxy $mysqlProxy, $coreBase)
    {
        return static::getDBProxy($mysqlProxy, $coreBase);
    }

    /**
     * 获取redis proxy
     *
     * @param IProxy|RedisProxyCluster|RedisProxyMasterSlave $redisProxy RedisProxy实例
     * @param Core $coreBase Core实例（通常为Controller实例）
     * @return Wrapper|\Redis AOP包装的RedisProxyCluster或者RedisProxyMasterSlave切片实例
     */
    public static function getRedisProxy(IProxy $redisProxy, $coreBase)
    {
        return static::getDBProxy($redisProxy, $coreBase);
    }

    /**
     * 获取对象池实例.
     *
     * @param Pool $pool Pool实例
     * @param Child $coreBase Core实例（通常为Controller实例）
     * @return Wrapper|Pool AOP包装的Pool切片实例
     */
    public static function getObjectPool(Pool $pool, $coreBase)
    {
        $AOPPool = new Wrapper($pool);

        $AOPPool->registerOnBefore(function ($method, $arguments) use ($coreBase) {
            if ($method === 'push') {
                // 手工处理释放资源
                method_exists($arguments[0], 'destroy') && $arguments[0]->destroy();
                // 自动调用析构方法
                method_exists($arguments[0], '__destruct') && $arguments[0]->__destruct();
                // 自动处理释放资源
                $class = get_class($arguments[0]);
                if (!empty(MI::$__reflections[$class]) && method_exists($arguments[0], 'resetProperties')) {
                    $arguments[0]->resetProperties();
                } else {
                    if (!empty(MI::$__reflections[$class]) && !empty(MI::$__reflections[$class][Macro::DS_PUBLIC])) {
                        foreach (MI::$__reflections[$class][Macro::DS_PUBLIC] as $prop => $val) {
                            $arguments[0]->{$prop} = $val;
                        }
                    }
                }
                $arguments[0]->__isConstruct = false;
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
                        if ($parentClassName == Task::class) {
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
                    if (getInstance()->processType == Macro::PROCESS_WORKER) {
                        array_unshift($arguments, TaskProxy::class);
                    }
                }
            }
            $data = [];
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
                $result->parent  = null;//暂时无方案
                $class = get_class($result);
                // 支持TaskProxy
                if ($class == TaskProxy::class) {
                    array_shift($arguments);
                    $result->taskName = $arguments[0];
                }
                // 自动调用构造方法
                if (method_exists($result, '__construct') && $result->__isConstruct == false) {
                    if (!isset($arguments[1])) {
                        $arguments[1] = [];
                    }
                    $result->__isConstruct = true;
                    $result->__construct(...$arguments[1]);
                }
                // 支持自动销毁成员变量
                MI::__supportAutoDestroy($class);
                // 对象资源销毁级别
                $result->__DSLevel = $arguments[2] ?? Macro::DS_PUBLIC;
            }
            $data = [];
            $data['method'] = $method;
            $data['arguments'] = $arguments;
            $data['result'] = $result;
            return $data;
        });

        return $AOPPool;
    }
}
