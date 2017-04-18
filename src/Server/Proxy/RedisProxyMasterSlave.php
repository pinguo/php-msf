<?php
/**
 * @desc:
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/12
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Proxy;


use PG\MSF\Server\CoreBase\SwooleException;

class RedisProxyMasterSlave implements IProxy
{
    private $name;
    private $pools;
    private $master;
    private $slaves;
    private $goodPools;

    public function __construct($name, $config)
    {
        $this->name = $name;
        $this->pools = $config['pools'];
        try {
            $this->startCheck();
            if (!$this->master) {
                throw new SwooleException('No master redis server in master-slave config!');
            }

            if (empty($this->slaves)) {
                throw new SwooleException('No slave redis server in master-slave config!');
            }
        } catch (SwooleException $e) {
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
                if (getInstance()->getAsynPool($pool)->getSync()
                    ->set('msf_active_master_slave_check', 1, 5)
                ) {
                    $this->master = $pool;
                    break;
                }
            } catch (\RedisException $e) {
                // do nothing
            }

        }

        if ($this->master === null) {
            return false;
        }

        //探测从节点
        if (count($this->pools) === 1) {
            $this->slaves[] = $this->master;
        } else {
            foreach ($this->pools as $pool) {
                if ($pool != $this->master) {
                    try {
                        if (getInstance()->getAsynPool($pool)->getSync()
                                ->get('msf_active_master_slave_check') == 1
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
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function handle($method, $arguments)
    {
        //读
        $lowerMethod = strtolower($method);
        if (strpos($lowerMethod, 'get') !== false ||
            strpos($lowerMethod, 'exists') !== false ||
            strpos($lowerMethod, 'range') !== false ||
            strpos($lowerMethod, 'count') !== false ||
            strpos($lowerMethod, 'size') !== false
        ) {
            $rand = array_rand($this->slaves);
            $redisPoolName = $this->slaves[$rand];
        } else {
            //写
            $redisPoolName = $this->master;
        }

        if (!isset(RedisProxy::$redisCoroutines[$redisPoolName])) {
            RedisProxy::$redisCoroutines[$redisPoolName] = getInstance()->getAsynPool($redisPoolName)->getCoroutine();
        }
        $redisPoolCoroutine = RedisProxy::$redisCoroutines[$redisPoolName];

        if ($method === 'cache') {
            return call_user_func_array([$redisPoolCoroutine, $method], $arguments);
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
            $yac = new \Yac('msf_config_redis_proxy_');
            $this->goodPools = $yac->get($this->name) ?? [];

            if (empty($this->goodPools)) {
                return false;
            }

            $newMaster = $this->goodPools['master'];
            $newSlaves = $this->goodPools['slaves'];

            if (empty($newMaster)) {
                throw new SwooleException('No master redis server in master-slave config!');
            }

            if ($this->master !== $newMaster) {
                $this->master = $newMaster;
                echo RedisProxyFactory::getLogTitle() . 'master node change to ' . $newMaster;
            }

            if (empty($newSlaves)) {
                throw new SwooleException('No slave redis server in master-slave config!');
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

        } catch (SwooleException $e) {
            echo RedisProxyFactory::getLogTitle() . $e->getMessage();
            return false;
        }
    }
}
