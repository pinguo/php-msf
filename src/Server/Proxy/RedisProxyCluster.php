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
    public function __construct($config)
    {

        $pools = $config['pools'];
        $hasher = $config['hasher'] ?? Md5Hasher::class;
        $hasher = new $hasher;
        try {
            parent::__construct($hasher);;
            $pools = $this->startCheck($pools);
            if (empty($pools)) {
                throw new SwooleException('No redis server can write in cluster');
            } else {
                foreach ($pools as $pool => $weight) {
                    $this->addTarget($pool, $weight);
                }
            }
        } catch (SwooleException $e) {
            echo $e->getMessage() . "\n";
        }
    }

    /**
     * 检查可用的pools
     * @param $pools
     * @return array
     */
    public function startCheck($pools)
    {
        $goodPools = [];
        foreach ($pools as $pool => $weight) {
            try {
                if (getInstance()->getAsynPool($pool)->getSync()
                    ->set('msf_active_cluster_check', 1, 30)
                ) {
                    $goodPools[$pool] = $weight;
                }
            } catch (\RedisException $e) {
                echo $e->getMessage() . "\t {$pool}\n";
            }
        }

        return $goodPools;
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

    public function check($pools)
    {
        // TODO: Implement check() method.
    }
}
