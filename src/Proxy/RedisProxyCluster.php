<?php
/**
 * 分布式结构Redis代理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

use Flexihash\Flexihash;
use Flexihash\Hasher\Md5Hasher;
use PG\MSF\Pools\RedisAsynPool;
use PG\MSF\Helpers\Context;

/**
 * Class RedisProxyCluster
 * @package PG\MSF\Proxy
 */
class RedisProxyCluster extends Flexihash implements IProxy
{
    /**
     * @var string 代理标识，它代表一个Redis集群
     */
    private $name;

    /**
     * @var array 连接池列表 key=连接池名称, value=权重
     */
    private $pools;

    /**
     * @var array 通过探活检测的连接池列表
     */
    private $goodPools = [];

    /**
     * @var mixed|string key前缀
     */
    private $keyPrefix = '';

    /**
     * @var bool|mixed 是否将key散列后储存
     */
    private $hashKey = false;
    /**
     * @var bool 随机选择一个redis，一般用于redis前面有twemproxy等代理，每个代理都可以处理请求，随机即可
     */
    private $isRandom = false;

    /**
     * RedisProxyCluster constructor.
     *
     * @param string $name 代理标识
     * @param array $config 代理配置数组
     */
    public function __construct(string $name, array $config)
    {
        $this->name      = $name;
        $this->pools     = $config['pools'];
        $this->keyPrefix = $config['keyPrefix'] ?? '';
        $this->hashKey   = $config['hashKey'] ?? false;
        $this->isRandom  = $config['random'] ?? false;
        $hasher          = $config['hasher'] ?? Md5Hasher::class;
        $hasher          = new $hasher;

        try {
            parent::__construct($hasher);
            $this->startCheck();
            if (empty($this->goodPools)) {
                throw new Exception('No redis server can write in cluster');
            } else {
                foreach ($this->goodPools as $pool => $weight) {
                    $this->addTarget($pool, $weight);
                }
            }
        } catch (Exception $e) {
            writeln('Redis Proxy ' . $e->getMessage());
        }
    }

    /**
     * 检测可用的连接池
     *
     * @return $this
     */
    public function startCheck()
    {
        $this->syncCheck();
        return $this;
    }

    /**
     * 启动时同步检测可用的连接池
     *
     * @return $this
     */
    private function syncCheck()
    {
        $this->goodPools = [];

        foreach ($this->pools as $pool => $weight) {
            try {
                $poolInstance = getInstance()->getAsynPool($pool);
                if (!$poolInstance) {
                    $poolInstance = new RedisAsynPool(getInstance()->config, $pool);
                    getInstance()->addAsynPool($pool, $poolInstance, true);
                }

                if ($poolInstance->getSync()->set('msf_active_cluster_check_' . gethostname(), 1, 5)) {
                    $this->goodPools[$pool] = $weight;
                } else {
                    $host = getInstance()->getAsynPool($pool)->getSync()->getHost();
                    $port = getInstance()->getAsynPool($pool)->getSync()->getPort();
                    getInstance()->getAsynPool($pool)->getSync()->connect($host, $port, 0.05);
                }
            } catch (\Exception $e) {
                writeln('Redis Proxy' . $e->getMessage() . "\t {$pool}");
            }
        }
    }

    /**
     * 发送异步Redis请求
     *
     * @param string $method Redis指令
     * @param array $arguments Redis指令参数
     * @return array|bool|mixed
     */
    public function handle(string $method, array $arguments)
    {
        /**
         * @var Context $arguments[0]
         */
        try {
            if ($this->isRandom) {
                return $this->random($method, $arguments);
            }

            if ($method === 'evalMock') {
                return $this->evalMock($arguments);
            } else {
                $key = $arguments[1];
                //单key操作
                if (!is_array($key)) {
                    return $this->single($method, $key, $arguments);
                    // 批量操作
                } else {
                    return $this->multi($method, $key, $arguments);
                }
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 执行Redis evalMock指令
     *
     * @param array $arguments evalMock指令参数
     * @return array
     */
    public function evalMock(array $arguments)
    {
        $args         = $arguments[2];
        $numKeys      = $arguments[3];
        $keys         = array_slice($args, 0, $numKeys);
        $evalMockArgs = array_slice($args, $numKeys);
        $arrRedis     = $index2Key = [];

        if (empty($keys)) {
            //如果没有设置缓存key，则连接所有的实例
            $arrRedis = $this->getAllTargets();
        } else {
            //根据脚本中用到的key计算出需要连接哪些实例
            foreach ($keys as $key) {
                $key = $this->generateUniqueKey($key);
                $redisPoolName = $this->lookup($key);

                $index = array_search($redisPoolName, $arrRedis, true);
                if ($index === false) {
                    $index = count($arrRedis);
                    $arrRedis[] = $redisPoolName;
                }
                $index2Key[$index][] = $key;
            }
        }

        $ret = [];
        foreach ($arrRedis as $index => $redisPoolName) {
            if (!isset(RedisProxyFactory::$redisCoroutines[$redisPoolName])) {
                if (getInstance()->getAsynPool($redisPoolName) == null) {
                    return [];
                }
                RedisProxyFactory::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
            }
            $redisPoolCoroutine = RedisProxyFactory::$redisCoroutines[$redisPoolName];

            $arrKeys = empty($index2Key) ? $index2Key : $index2Key[$index];
            $res     = $redisPoolCoroutine->evalMock($arguments[0], $arguments[1], array_merge($arrKeys, $evalMockArgs), count($arrKeys));

            if ($res instanceof \Generator) {
                $ret[] = $res;
            }
        }

        foreach ($ret as $k => $item) {
            $ret[$k] = yield $item;
        }

        return $ret;
    }

    /**
     * 生成唯一Redis Key
     *
     * @param string $key Key
     * @return string
     */
    private function generateUniqueKey(string $key)
    {
        return $this->hashKey ? md5($this->keyPrefix . $key) : $this->keyPrefix . $key;
    }

    /**
     * 随机策略
     *
     * @param string $method Redis指令
     * @param array $arguments Redis指令参数
     * @return bool
     */
    private function random(string $method, array $arguments)
    {
        $redisPoolName = array_rand($this->goodPools);

        if (!isset(RedisProxyFactory::$redisCoroutines[$redisPoolName])) {
            if (getInstance()->getAsynPool($redisPoolName) == null) {
                return false;
            }
            RedisProxyFactory::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
        }
        $redisPoolCoroutine = RedisProxyFactory::$redisCoroutines[$redisPoolName];

        return $redisPoolCoroutine->$method(...$arguments);
    }

    /**
     * 单key指令
     *
     * @param string $method Redis指令
     * @param string $key Redis Key
     * @param array $arguments Redis指令参数
     * @return mixed
     */
    private function single(string $method, string $key, array $arguments)
    {
        $redisPoolName = $this->lookup($this->generateUniqueKey($key));

        if (!isset(RedisProxyFactory::$redisCoroutines[$redisPoolName])) {
            if (getInstance()->getAsynPool($redisPoolName) == null) {
                return false;
            }
            RedisProxyFactory::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
        }
        $redisPoolCoroutine = RedisProxyFactory::$redisCoroutines[$redisPoolName];

        if ($method === 'cache') {
            $result = $redisPoolCoroutine->$method(...$arguments);
        } else {
            $result = $redisPoolCoroutine->__call($method, $arguments);
        }

        return $result;
    }

    /**
     * 批量多key指令
     *
     * @param string $method Redis指令
     * @param array $key Redis Key列表
     * @param array $arguments Redis指令参数
     * @return array|bool
     */
    private function multi(string $method, array $key, array $arguments)
    {
        $opArr = [];

        if (in_array(strtolower($method), ['mset', 'msetnx'])) {
            foreach ($key as $k => $v) {
                $redisPoolName = $this->lookup($this->generateUniqueKey($k));
                $opArr[$redisPoolName][$k] = $v;
            }

            $opData = $this->dispatch($opArr, $method, $arguments);

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
                $redisPoolName = $this->lookup($this->generateUniqueKey($k));
                $opArr[$redisPoolName][] = $k;
            }

            $opData = $this->dispatch($opArr, $method, $arguments);

            foreach ($opData as $redisPoolName => $op) {
                $values = yield $op;
                if (is_array($values) && !empty($values)) { //$values有可能超时返回false
                    $retData = array_merge($retData, $values);
                }
            }

            return $retData;
        }
    }

    /**
     * 请求分发
     *
     * @param array $opArr 相应Redis连接池的所有请求
     * @param string $method Redis指令
     * @param array $arguments Redis指令参数
     * @return array
     */
    protected function dispatch(array $opArr, string $method, array $arguments)
    {
        $opData = [];
        foreach ($opArr as $redisPoolName => $op) {
            if (!isset(RedisProxyFactory::$redisCoroutines[$redisPoolName])) {
                if (getInstance()->getAsynPool($redisPoolName) == null) {
                    return [];
                }
                RedisProxyFactory::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
            }
            $redisPoolCoroutine = RedisProxyFactory::$redisCoroutines[$redisPoolName];

            if ($method === 'cache') {
                $opData[$redisPoolName] = $redisPoolCoroutine->$method(...[$arguments[0], $op]);
            } else {
                $opData[$redisPoolName] = $redisPoolCoroutine->__call($method, [$arguments[0], $op]);
            }
        }

        return $opData;
    }

    /**
     * 用户定时检测
     *
     * @return bool
     */
    public function check()
    {
        $this->goodPools = getInstance()->sysCache->get($this->name) ?? [];
        if (!$this->goodPools) {
            return false;
        }

        $nowPools = $this->getAllTargets();
        $newPools = array_keys($this->goodPools);
        $loses    = array_diff($nowPools, $newPools);

        if (!empty($loses)) {
            foreach ($loses as $lost) {
                $this->removeTarget($lost);
            }
            writeln('Redis Proxy Remove ( ' . implode(',', $loses) . ' ) from Cluster');
        }

        $adds = array_diff($newPools, $nowPools);
        if (!empty($adds)) {
            foreach ($adds as $add) {
                $this->addTarget($add, $this->pools[$add]);
            }
            writeln('Redis Proxy Add ( ' . implode(',', $adds) . ' ) into Cluster');
        }

        return true;
    }
}
