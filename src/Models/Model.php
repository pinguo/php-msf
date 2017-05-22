<?php
/**
 * Model 涉及到数据有关的处理
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Models;

use PG\AOP\Wrapper;
use PG\MSF\Base\Core;
use PG\MSF\Base\AOPFactory;

class Model extends Core
{
    /**
     * redis连接池
     * @var array
     */
    private $redisPools;
    /**
     * redis代理池
     * @var array
     */
    private $redisProxies;

    final public function __construct()
    {
        parent::__construct();
    }

    /**
     * 当被loader时会调用这个方法进行初始化
     * @param \PG\MSF\Helpers\Context $context
     */
    public function initialization($context)
    {
        $this->setContext($context);
    }

    /**
     * 销毁回归对象池
     */
    public function destroy()
    {
        $this->redisProxies = null;
        $this->redisPools = null;
        parent::destroy();
        ModelFactory::getInstance()->revertModel($this);
    }

    /**
     * 获取redis连接池
     * @param string $poolName
     * @return bool|Wrapper|\PG\MSF\DataBase\CoroutineRedisHelp
     */
    protected function getRedisPool(string $poolName)
    {
        if (isset($this->redisPools[$poolName])) {
            return $this->redisPools[$poolName];
        }
        $pool = getInstance()->getAsynPool($poolName);
        if (!$pool) {
            return false;
        }

        $this->redisPools[$poolName] = AOPFactory::getRedisPoolCoroutine($pool->getCoroutine(), $this);
        return $this->redisPools[$poolName];
    }

    /**
     * 获取redis代理
     * @param string $proxyName
     * @return bool|Wrapper
     */
    protected function getRedisProxy(string $proxyName)
    {
        if (isset($this->redisProxies[$proxyName])) {
            return $this->redisProxies[$proxyName];
        }
        $proxy = getInstance()->getRedisProxy($proxyName);
        if (!$proxy) {
            return false;
        }

        $this->redisProxies[$proxyName] = AOPFactory::getRedisProxy($proxy, $this);
        return $this->redisProxies[$proxyName];
    }
}
