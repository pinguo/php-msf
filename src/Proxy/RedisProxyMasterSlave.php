<?php
/**
 * @desc:
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/12
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

use Exception;
use PG\MSF\DataBase\RedisAsynPool;

class RedisProxyMasterSlave implements IProxy
{
    private $name;
    private $pools;
    private $master;
    private $slaves;
    private $goodPools;

    private static $readOperation = [
        // Strings
        'GET', 'MGET', 'BITCOUNT', 'STRLEN', 'GETBIT', 'GETRANGE',
        // Keys
        'KEYS', 'TYPE', 'SCAN', 'EXISTS', 'PTTL', 'TTL',
        // Hashes
        'HEXISTS', 'HGETALL', 'HKEYS', 'HLEN', 'HGET', 'HMGET',
        // Set
        'SISMEMBER', 'SMEMBERS', 'SRANDMEMBER', 'SSCAN', 'SCARD', 'SDIFF', 'SINTER',
        // List
        'LINDEX', 'LLEN', 'LRANGE',
        // Sorted Set
        'ZCARD', 'ZCOUNT', 'ZRANGE', 'ZRANGEBYSCORE', 'ZRANK', 'ZREVRANGE', 'ZREVRANGEBYSCORE',
        'ZREVRANK', 'ZSCAN', 'ZSCORE',
    ];

    /**
     * RedisProxyMasterSlave constructor.
     * @param string $name
     * @param array $config
     */
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->pools = $config['pools'];
        try {
            $this->startCheck();
            if (!$this->master) {
                throw new Exception('No master redis server in master-slave config!');
            }

            if (empty($this->slaves)) {
                throw new Exception('No slave redis server in master-slave config!');
            }
        } catch (Exception $e) {
            echo RedisProxyFactory::getLogTitle() . $e->getMessage();
        }
    }

    /**
     * 前置检测
     * @return bool
     */
    public function startCheck()
    {
        //探测主节点
        foreach ($this->pools as $pool) {
            try {
                $poolInstance = getInstance()->getAsynPool($pool);
                if (!$poolInstance) {
                    $poolInstance = new RedisAsynPool(getInstance()->config, $pool);
                    getInstance()->addAsynPool($pool, $poolInstance, true);
                }

                if ($poolInstance->getSync()
                    ->set('msf_active_master_slave_check_' . gethostname(), 1, 5)
                ) {
                    $this->master = $pool;
                    break;
                }
            } catch (\RedisException $e) {
                // do nothing
            }
        }

        //探测从节点
        if (count($this->pools) === 1) {
            $this->slaves[] = $this->master;
        } else {
            foreach ($this->pools as $pool) {
                $poolInstance = getInstance()->getAsynPool($pool);
                if (!$poolInstance) {
                    $poolInstance = new RedisAsynPool(getInstance()->config, $pool);
                    getInstance()->addAsynPool($pool, $poolInstance, true);
                }

                if ($pool != $this->master) {
                    try {
                        if ($poolInstance->getSync()
                                ->get('msf_active_master_slave_check_' . gethostname()) == 1
                        ) {
                            $this->slaves[] = $pool;
                        }
                    } catch (\RedisException $e) {
                        // do nothing
                    }
                }
            }
        }

        if (empty($this->slaves)) {
            return false;
        }

        return true;
    }

    /**
     * 处理入口
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function handle(string $method, array $arguments)
    {
        $upMethod = strtoupper($method);
        //读
        if (in_array($upMethod, self::$readOperation)) {
            $rand = array_rand($this->slaves);
            $redisPoolName = $this->slaves[$rand];
        } else {
            //写
            $redisPoolName = $this->master;
        }

        if (!isset(RedisProxyFactory::$redisCoroutines[$redisPoolName])) {
            if (getInstance()->getAsynPool($redisPoolName) == null) {
                return false;
            }
            RedisProxyFactory::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
        }
        $redisPoolCoroutine = RedisProxyFactory::$redisCoroutines[$redisPoolName];

        if ($method === 'cache' || $method === 'evalMock') {
            return $redisPoolCoroutine->$method(...$arguments);
        } else {
            return $redisPoolCoroutine->__call($method, $arguments);
        }
    }

    /**
     * 检测 用于定时检测
     * @return bool
     */
    public function check()
    {
        try {
            $this->goodPools = getInstance()->sysCache->get($this->name) ?? [];

            if (empty($this->goodPools)) {
                return false;
            }

            $newMaster = $this->goodPools['master'];
            $newSlaves = $this->goodPools['slaves'];

            if (empty($newMaster)) {
                echo RedisProxyFactory::getLogTitle() . 'No master redis server in master-slave config!';
                throw new Exception('No master redis server in master-slave config!');
            }

            if ($this->master !== $newMaster) {
                $this->master = $newMaster;
                echo RedisProxyFactory::getLogTitle() . 'master node change to ' . $newMaster;
            }

            if (empty($newSlaves)) {
                echo RedisProxyFactory::getLogTitle() . 'No slave redis server in master-slave config!';
                throw new Exception('No slave redis server in master-slave config!');
            }

            $losts = array_diff($this->slaves, $newSlaves);
            if ($losts) {
                $this->slaves = $newSlaves;
                echo RedisProxyFactory::getLogTitle() . 'slave nodes change to ( ' . implode(',',
                        $newSlaves) . ' ), lost ( ' . implode(',', $losts) . ' )';
            }

            $adds = array_diff($newSlaves, $this->slaves);
            if ($adds) {
                $this->slaves = $newSlaves;
                echo RedisProxyFactory::getLogTitle() . 'slave nodes change to ( ' . implode(',',
                        $newSlaves) . ' ), add ( ' . implode(',', $adds) . ' )';
            }

            return true;
        } catch (Exception $e) {
            echo RedisProxyFactory::getLogTitle() . $e->getMessage();
            return false;
        }
    }
}
