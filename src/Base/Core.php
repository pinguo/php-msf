<?php
/**
 * 内核基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use Noodlehaus\Config;
use PG\MSF\Pack\IPack;
use PG\MSF\DataBase\RedisAsynPool;
use PG\AOP\Wrapper;
use PG\MSF\Proxy\RedisProxyFactory;
use PG\MSF\DataBase\CoroutineRedisProxy;
use Exception;

class Core extends Child
{
    /**
     * @var int
     */
    public $__useCount;

    /**
     * @var int
     */
    public $__genTime;

    /**
     * 销毁标志
     * @var bool
     */
    protected $__isDestroy = false;

    /**
     * @var null
     */
    public static $stdClass = null;

    /**
     * redis连接池
     *
     * @var array
     */
    protected $redisPools;

    /**
     * redis代理池
     *
     * @var array
     */
    protected $redisProxies;

    /**
     * 构造方法
     */
    public function __construct()
    {

    }

    /**
     * 支持自动销毁public修饰的成员属性
     */
    public function __supportAutoDestroy()
    {
        if (empty(Child::$reflections[static::class])) {
            $reflection  = new \ReflectionClass(static::class);
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
            unset($autoDestroy['__useCount']);
            unset($autoDestroy['__genTime']);
            unset($autoDestroy['__coreName']);
            Child::$reflections[static::class] = $autoDestroy;
            unset($reflection);
            unset($default);
            unset($ps);
            unset($ss);
        }
    }

    /**
     * sleep
     *
     * @return array
     */
    public function __sleep()
    {
        return ['__useCount', '__genTime'];
    }

    /**
     * @return \swoole_server
     */
    public function getServer()
    {
        return getInstance()->server;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return getInstance()->config;
    }

    /**
     * @return IPack
     */
    public function getPack()
    {
        return getInstance()->pack;
    }

    /**
     * 获取redis连接池
     * @param string $poolName
     * @return bool|Wrapper|CoroutineRedisProxy|\Redis
     */
    public function getRedisPool(string $poolName)
    {
        if (isset($this->redisPools[$poolName])) {
            return $this->redisPools[$poolName];
        }

        $pool = getInstance()->getAsynPool($poolName);
        if (!$pool) {
            $pool = new RedisAsynPool($this->getConfig(), $poolName);
            getInstance()->addAsynPool($poolName, $pool, true);
        }

        $this->redisPools[$poolName] = AOPFactory::getRedisPoolCoroutine($pool->getCoroutine(), $this);
        return $this->redisPools[$poolName];
    }

    /**
     * 获取redis代理
     *
     * @param string $proxyName
     * @return bool|Wrapper|CoroutineRedisProxy|\Redis
     * @throws Exception
     */
    public function getRedisProxy(string $proxyName)
    {
        if (isset($this->redisProxies[$proxyName])) {
            return $this->redisProxies[$proxyName];
        }

        $proxy = getInstance()->getRedisProxy($proxyName);
        if (!$proxy) {
            $config = $this->getConfig()->get('redis_proxy.' . $proxyName, null);
            if (!$config) {
                throw new Exception("config redis_proxy.$proxyName not exits");
            }
            $proxy = RedisProxyFactory::makeProxy($proxyName, $config);
            getInstance()->addRedisProxy($proxyName, $proxy);
        }

        $this->redisProxies[$proxyName] = AOPFactory::getRedisProxy($proxy, $this);
        return $this->redisProxies[$proxyName];
    }

    /**
     * 设置RedisPools
     *
     * @param array $redisPools
     * @return $this
     */
    public function setRedisPools($redisPools)
    {
        if (!empty($this->redisPools)) {
            foreach ($this->redisPools as $k => &$pool) {
                $pool->destroy();
                $poll = null;
            }
        }

        $this->redisPools = $redisPools;
        return $this;
    }

    /**
     * 设置RedisPools
     *
     * @param array $redisProxies
     * @return $this
     */
    public function setRedisProxies($redisProxies)
    {
        if (!empty($this->redisProxies)) {
            foreach ($this->redisProxies as $k => &$proxy) {
                $proxy->destroy();
                $proxy = null;
            }
        }

        $this->redisProxies = $redisProxies;
        return $this;
    }

    /**
     * 销毁,解除引用
     */
    public function destroy()
    {
        if (!$this->__isDestroy) {
            parent::destroy();
            $this->__isDestroy = true;
        }
    }

    /**
     * 对象已使用标识
     */
    public function isUse()
    {
        $this->__isDestroy = false;
    }

    /**
     * 是否已经执行destroy
     *
     * @return bool
     */
    public function getIsDestroy()
    {
        return $this->__isDestroy;
    }
}
