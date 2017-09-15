<?php
/**
 * 主从结构Redis代理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

use PG\MSF\Pools\RedisAsynPool;

/**
 * Class RedisProxyMasterSlave
 * @package PG\MSF\Proxy
 */
class RedisProxyMasterSlave implements IProxy
{
    /**
     * @var string 代理标识，它代表一个Redis集群
     */
    private $name;

    /**
     * @var array 连接池列表，数字索引的连接池名称列表
     */
    private $pools;

    /**
     * @var string Redis集群中主节点的连接池名称
     */
    private $master;

    /**
     * @var array Redis集群中从节点的连接池名称列表
     */
    private $slaves;

    /**
     * @var array 通过探活检测的连接池列表
     */
    private $goodPools;

    /**
     * @var array 读的Redis指令列表
     */
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
     *
     * @param string $name 代理标识
     * @param array $config 配置对象
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
            writeln('Redis Proxy ' . $e->getMessage());
        }
    }

    /**
     * 启动时检测Redis集群状态
     *
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
     * 发送异步Redis请求
     *
     * @param string $method Redis指令
     * @param array $arguments Redis指令参数
     * @return mixed
     */
    public function handle(string $method, array $arguments)
    {
        $upMethod = strtoupper($method);
        //读
        if (in_array($upMethod, self::$readOperation) && !empty($this->slaves)) {
            $rand          = array_rand($this->slaves);
            $redisPoolName = $this->slaves[$rand];
        } else {
            //写
            $redisPoolName = $this->master;
        }

        // EVALMOCK在指定了脚本仅读操作时，可以在从节点上执行
        if ($upMethod == 'EVALMOCK' && isset($arguments[4])) {
            if ($arguments[4]) {
                $rand          = array_rand($this->slaves);
                $redisPoolName = $this->slaves[$rand];
            }

            array_pop($arguments);
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
     * 定时检测
     *
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
                writeln('Redis Proxy No master redis server in master-slave config!');
                throw new Exception('No master redis server in master-slave config!');
            }

            if ($this->master !== $newMaster) {
                $this->master = $newMaster;
                writeln('Redis Proxy master node change to ' . $newMaster);
            }

            if (empty($newSlaves)) {
                writeln('Redis Proxy No slave redis server in master-slave config!');
                throw new Exception('No slave redis server in master-slave config!');
            }

            $loses = array_diff($this->slaves, $newSlaves);
            if ($loses) {
                $this->slaves = $newSlaves;
                writeln('Redis Proxy slave nodes change to ( ' . implode(
                    ',',
                    $newSlaves
                ) . ' ), lost ( ' . implode(',', $loses) . ' )');
            }

            $adds = array_diff($newSlaves, $this->slaves);
            if ($adds) {
                $this->slaves = $newSlaves;
                writeln('Redis Proxy slave nodes change to ( ' . implode(
                    ',',
                    $newSlaves
                ) . ' ), add ( ' . implode(',', $adds) . ' )');
            }

            return true;
        } catch (Exception $e) {
            writeln('Redis Proxy ' . $e->getMessage());
            return false;
        }
    }
}
