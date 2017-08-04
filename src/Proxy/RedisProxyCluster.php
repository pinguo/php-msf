<?php
/**
 * @desc:
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/12
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

use Flexihash\Flexihash;
use Flexihash\Hasher\Md5Hasher;
use Exception;
use PG\MSF\DataBase\RedisAsynPool;

class RedisProxyCluster extends Flexihash implements IProxy
{
    private $name;

    private $pools;

    private $goodPools = [];

    private $keyPrefix = '';
    private $hashKey = false;

    /**
     * RedisProxyCluster constructor.
     * @param string $name
     * @param array $config
     */
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->pools = $config['pools'];
        $hasher = $config['hasher'] ?? Md5Hasher::class;
        $hasher = new $hasher;
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
            echo RedisProxyFactory::getLogTitle() . $e->getMessage();
        }
    }

    /**
     * 检查可用的pools
     * @return array
     */
    public function startCheck()
    {
        $this->syncCheck();
    }

    /**
     * 同步检测 用户启动
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

                if ($poolInstance->getSync()
                    ->set('msf_active_cluster_check_' . gethostname(), 1, 5)
                ) {
                    $this->goodPools[$pool] = $weight;
                } else {
                    $host = getInstance()->getAsynPool($pool)->getSync()->getHost();
                    $port = getInstance()->getAsynPool($pool)->getSync()->getPort();
                    getInstance()->getAsynPool($pool)->getSync()->connect($host, $port, 0.05);
                }
            } catch (\Exception $e) {
                echo RedisProxyFactory::getLogTitle() . $e->getMessage() . "\t {$pool}\n";
            }
        }
    }

    /**
     * 处理查询
     * @param string $method
     * @param array $arguments
     * @return array|bool|mixed
     */
    public function handle(string $method, array $arguments)
    {
        try {
            if ($method === 'evalMock') {
                return $this->evalMock($arguments);
            } else {
                $key = $arguments[1];
                //单key操作
                if (!is_array($key)) {
                    return $this->single($method, $key, $arguments);
                } else {
                    // 批量操作
                    return $this->multi($method, $key, $arguments);
                }
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param array $arguments
     * @return array
     */
    public function evalMock(array $arguments)
    {
        $args = $arguments[2];
        $numKeys = $arguments[3];
        $keys = array_slice($args, 0, $numKeys);
        $argvs = array_slice($args, $numKeys);

        $arrRedis = $index2Key = array();

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

        $ret = array();
        foreach ($arrRedis as $index => $redisPoolName) {
            if (!isset(RedisProxyFactory::$redisCoroutines[$redisPoolName])) {
                if (getInstance()->getAsynPool($redisPoolName) == null) {
                    return [];
                }
                RedisProxyFactory::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
            }
            $redisPoolCoroutine = RedisProxyFactory::$redisCoroutines[$redisPoolName];

            $arrKeys = empty($index2Key) ? $index2Key : $index2Key[$index];
            $res = $redisPoolCoroutine->evalMock($arguments[0], $arguments[1], array_merge($arrKeys, $argvs), count($arrKeys));
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
     * @param string $key a key identifying a value to be cached
     * @return string a key generated from the provided key which ensures the uniqueness across applications
     */
    private function generateUniqueKey(string $key)
    {
        return $this->hashKey ? md5($this->keyPrefix . $key) : $this->keyPrefix . $key;
    }

    /**
     * 单key
     * @param string $method
     * @param string $key
     * @param array $arguments
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
     * 批量处理
     * @param string $method
     * @param array $key
     * @param array $arguments
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
     * @param string $method
     * @param array $arguments
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
     * 检测  用户定时检测
     */
    public function check()
    {
        $this->goodPools = getInstance()->sysCache->get($this->name) ?? [];
        if (!$this->goodPools) {
            return false;
        }

        $nowPools = $this->getAllTargets();
        $newPools = array_keys($this->goodPools);
        $losts = array_diff($nowPools, $newPools);
        if (!empty($losts)) {
            foreach ($losts as $lost) {
                $this->removeTarget($lost);
            }
            echo RedisProxyFactory::getLogTitle() . ' Remove ( ' . implode(',', $losts) . ' ) from Cluster';
        }

        $adds = array_diff($newPools, $nowPools);
        if (!empty($adds)) {
            foreach ($adds as $add) {
                $this->addTarget($add, $this->pools[$add]);
            }
            echo RedisProxyFactory::getLogTitle() . ' Add ( ' . implode(',', $adds) . ' ) into Cluster';
        }
    }
}
