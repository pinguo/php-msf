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
use PG\AOP\Wrapper;
use PG\MSF\Pools\Miner;
use PG\MSF\Pools\RedisAsynPool;
use PG\MSF\Pools\MysqlAsynPool;
use PG\MSF\Proxy\RedisProxyFactory;
use PG\MSF\Proxy\MysqlProxyFactory;
use PG\MSF\Pools\CoroutineRedisProxy;
use PG\MSF\Proxy\MysqlProxyMasterSlave;

/**
 * Class Core
 * @package PG\MSF\Base
 */
class Core extends Child
{
    /**
     * @var int 使用计数
     */
    public $__useCount;

    /**
     * @var int 创建时间
     */
    public $__genTime;

    /**
     * @var bool 是否执行构造方法
     */
    public $__isConstruct = false;

    /**
     * @var bool 销毁标志
     */
    protected $__isDestroy = false;

    /**
     * @var \stdClass|null 对象模板
     */
    public static $stdClass = null;

    /**
     * @var array redis连接池
     */
    protected $redisPools;

    /**
     * @var array redis代理池
     */
    protected $redisProxies;

    /**
     * @var array mysql连接池
     */
    protected $mysqlPools;

    /**
     * @var array mysql代理池
     */
    protected $mysqlProxies;

    /**
     * 构造方法
     */
    public function __construct()
    {
    }

    /**
     * 在序列化及dump对象时使用，代表哪些属于需要导出
     *
     * @return array
     */
    public function __sleep()
    {
        return [];
    }

    /**
     * 和__sleep作用相反
     */
    public function __unsleep()
    {
        return [];
    }

    /**
     * 获取运行Server实例
     *
     * @return \swoole_server
     */
    public function getServerInstance()
    {
        return getInstance()->server;
    }

    /**
     * 获取运行server实例配置对象
     *
     * @return Config
     */
    public function getConfig()
    {
        return getInstance()->config;
    }

    /**
     * 获取运行server实例打包对象
     *
     * @return IPack
     */
    public function getPack()
    {
        return getInstance()->pack;
    }

    /**
     * 获取Redis连接池
     *
     * @param string $poolName 配置的Redis连接池名称
     * @return bool|Wrapper|CoroutineRedisProxy|\Redis
     */
    public function getRedisPool(string $poolName)
    {
        $activePoolName = $poolName;
        $poolName       = RedisAsynPool::ASYN_NAME . $poolName;
        if (isset($this->redisPools[$poolName])) {
            return $this->redisPools[$poolName];
        }

        $pool = getInstance()->getAsynPool($poolName);
        if (!$pool) {
            $pool = new RedisAsynPool($this->getConfig(), $activePoolName);
            getInstance()->addAsynPool($poolName, $pool, true);
        }

        $this->redisPools[$poolName] = AOPFactory::getRedisPoolCoroutine($pool->getCoroutine(), $this);
        return $this->redisPools[$poolName];
    }

    /**
     * 获取Redis代理
     *
     * @param string $proxyName 配置的Redis代理名称
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
            if (!$proxy) {
                throw new Exception('make proxy failed, please check your proxy config.');
            }
            getInstance()->addRedisProxy($proxyName, $proxy);
        }

        $this->redisProxies[$proxyName] = AOPFactory::getRedisProxy($proxy, $this);
        return $this->redisProxies[$proxyName];
    }

    /**
     * 获取MySQL连接池
     *
     * @param string $poolName 配置的MySQL连接池名称
     * @return MysqlAsynPool|Miner|Wrapper
     */
    public function getMysqlPool(string $poolName)
    {
        $activePoolName = $poolName;
        $poolName       = MysqlAsynPool::ASYN_NAME . $poolName;
        if (isset($this->mysqlPools[$poolName])) {
            return $this->mysqlPools[$poolName];
        }

        $pool = getInstance()->getAsynPool($poolName);
        if (!$pool) {
            $pool = new MysqlAsynPool($this->getConfig(), $activePoolName);
            getInstance()->addAsynPool($poolName, $pool, true);
        }

        $this->mysqlPools[$poolName] = AOPFactory::getMysqlPoolCoroutine($pool, $this);
        return $this->mysqlPools[$poolName];
    }

    /**
     * 获取Mysql代理
     *
     * @param string $proxyName 配置的MySQL代理名称
     * @return Wrapper|mysqlProxyMasterSlave|Miner|MysqlAsynPool
     * @throws Exception
     */
    public function getMysqlProxy(string $proxyName)
    {
        if (isset($this->mysqlProxies[$proxyName])) {
            return $this->mysqlProxies[$proxyName];
        }

        $proxy = getInstance()->getMysqlProxy($proxyName);
        if (!$proxy) {
            $config = $this->getConfig()->get('mysql_proxy.' . $proxyName, null);
            if (!$config) {
                throw new Exception("config mysql_proxy.$proxyName not exits");
            }
            $proxy = MysqlProxyFactory::makeProxy($proxyName, $config);
            if (!$proxy) {
                throw new Exception('make proxy failed, please check your proxy config.');
            }
            getInstance()->addMysqlProxy($proxyName, $proxy);
        }

        $this->mysqlProxies[$proxyName] = AOPFactory::getMysqlProxy($proxy, $this);
        return $this->mysqlProxies[$proxyName];
    }

    /**
     * 设置RedisPools
     *
     * @param array|null $redisPools 多个Redis连接池实例，通常用于销毁Redis连接池，赋值为NULL
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
     * @param array|null $redisProxies 多个Redis代理实例，通常用于销毁Redis代理，赋值为NULL
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
