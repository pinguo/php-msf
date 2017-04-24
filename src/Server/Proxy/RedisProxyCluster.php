<?php
/**
 * @desc:
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/12
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Proxy;

use Flexihash\{
    Flexihash, Hasher\Md5Hasher
};
use PG\MSF\Server\CoreBase\SwooleException;

class RedisProxyCluster extends Flexihash implements IProxy
{
    private $name;

    private $pools;

    private $goodPools = [];

    public function __construct($name, $config)
    {
        $this->name = $name;
        $this->pools = $config['pools'];
        $hasher = $config['hasher'] ?? Md5Hasher::class;
        $hasher = new $hasher;
        try {
            parent::__construct($hasher);;
            $this->startCheck();
            if (empty($this->goodPools)) {
                throw new SwooleException('No redis server can write in cluster');
            } else {
                foreach ($this->goodPools as $pool => $weight) {
                    $this->addTarget($pool, $weight);
                }
            }
        } catch (SwooleException $e) {
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
     * 处理查询
     * @param $method
     * @param $arguments
     * @return array|bool|mixed
     */
    public function handle($method, $arguments)
    {
        try {
            $key = $arguments[1];
            //单key操作
            if (!is_array($key)) {
                return $this->single($method, $key, $arguments);
            } else {
                // 批量操作
                return $this->multi($method, $key, $arguments);
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 单key
     * @param $method
     * @param $key
     * @param $arguments
     * @return mixed
     */
    private function single($method, $key, $arguments)
    {
        $redisPoolName = $this->lookup($key);

        if (!isset(RedisProxyFactory::$redisCoroutines[$redisPoolName])) {
            RedisProxyFactory::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
        }
        $redisPoolCoroutine = RedisProxyFactory::$redisCoroutines[$redisPoolName];

        if ($method === 'cache') {
            $result = call_user_func_array([$redisPoolCoroutine, $method], $arguments);
        } else {
            $result = $redisPoolCoroutine->__call($method, $arguments);
        }

        return $result;
    }

    /**
     * 批量处理
     * @param $method
     * @param $key
     * @param $arguments
     * @return array|bool
     */
    private function multi($method, $key, $arguments)
    {
        $opArr = [];

        if (in_array(strtolower($method), ['mset', 'msetnx'])) {
            foreach ($key as $k => $v) {
                $redisPoolName = $this->lookup($k);
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
                $redisPoolName = $this->lookup($k);
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
     * @param $method
     * @param $arguments
     * @return array
     */
    protected function dispatch(array $opArr, $method, $arguments)
    {
        $opData = [];
        foreach ($opArr as $redisPoolName => $op) {
            if (!isset(RedisProxyFactory::$redisCoroutines[$redisPoolName])) {
                RedisProxyFactory::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
            }
            $redisPoolCoroutine = RedisProxyFactory::$redisCoroutines[$redisPoolName];

            if ($method === 'cache') {
                $opData[$redisPoolName] = call_user_func_array([$redisPoolCoroutine, $method],
                    [$arguments[0], $op]);
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

    /**
     * 同步检测 用户启动
     */
    private function syncCheck()
    {
        $this->goodPools = [];
        foreach ($this->pools as $pool => $weight) {
            try {
                if (getInstance()->getAsynPool($pool)->getSync()
                    ->set('msf_active_cluster_check', 1, 5)
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
}
