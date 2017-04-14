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
    private $pools;
    private $master;
    private $slaves;

    private $asyncCheckResult;

    public function __construct($config)
    {
        $this->pools = $config['pools'];
        try {
            $this->startCheck();
            if (!$this->master) {
                throw new SwooleException('No master redis server in master-slave config!');
            }

            if (empty($this->slaves)) {
                echo 111;
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

    public function check()
    {
        if (getInstance()->isTaskWorker()) {
            $this->syncCheck();
        } else {
            $this->asyncCheck();
        }
    }

    private function syncCheck()
    {
        try {
            //探测主节点
            $newMaster = '';
            foreach ($this->pools as $pool) {
                try {
                    if (getInstance()->getAsynPool($pool)->getSync()
                        ->set('msf_active_master_slave_check', 1, 5)
                    ) {
                        $newMaster = $pool;
                        break;
                    }
                } catch (\RedisException $e) {
                    // do nothing
                }

            }

            if ($newMaster === '') {
                throw new SwooleException('No master redis server in master-slave config!');
            }
            if ($this->master !== $newMaster) {
                $this->master = $newMaster;
                echo RedisProxyFactory::getLogTitle() . 'master node change to ' . $newMaster;
            }

            //探测从节点
            $newSlaves = [];
            if (count($this->pools) === 1) {
                $newSlaves[] = $this->master;
            } else {
                if (count($this->pools) == 1) {
                    $newSlaves[] = $this->master;
                } else {
                    foreach ($this->pools as $pool) {
                        if ($pool != $this->master) {
                            try {
                                if (getInstance()->getAsynPool($pool)->getSync()
                                        ->get('msf_active_master_slave_check') == 1
                                ) {
                                    $newSlaves[] = $pool;
                                }
                            } catch (\RedisException $e) {
                                // do nothing
                            }
                        }
                    }
                }
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

    private function asyncCheck()
    {
        $this->asyncCheckResult = [];
        $this->asyncCheckResult['pools'] = [];
        $this->asyncCheckResult['master'] = '';
        $this->asyncCheckResult['slaves'] = [];

        try {
            foreach ($this->pools as $pool) {
                $conf = getInstance()->config->get('redis.' . $pool);

                $client = new \swoole_redis;

                $this->asyncCheckResult['pools'][$pool] = $client;

                $client->connect($conf['ip'], $conf['port'], function ($client, $result) use ($pool) {
                    if ($result != false) {
                        //探测主节点
                        $client->setex('msf_active_master_slave_check', 5, 1, function ($client, $result) use ($pool) {
                            if ($result == 'OK') {
                                $this->asyncCheckResult['master'] = $pool;

                                //有主节点 检查从
                                if (count($this->pools) > 1) {
                                    foreach ($this->asyncCheckResult['pools'] as $p => $cli) {
                                        if ($p !== $this->asyncCheckResult['master']) {
                                            $cli->get('msf_active_master_slave_check',
                                                function ($client, $result) use ($p) {
                                                    if ($result == 1) {
                                                        $this->asyncCheckResult['slaves'][] = $p;
                                                    }
                                                });
                                        }

                                    }
                                } else {
                                    $this->asyncCheckResult['slaves'][] = $pool;
                                }

                            }
                        });
                    }
                });
            }

            //50ms就关闭连接
            swoole_timer_after(50, function () {
                if ($this->asyncCheckResult['master'] === '') {
                    echo RedisProxyFactory::getLogTitle() . 'No master redis server in master-slave config!';
                }
                if ($this->master !== $this->asyncCheckResult['master']) {
                    $this->master = $this->asyncCheckResult['master'];
                    echo RedisProxyFactory::getLogTitle() . 'master node change to ' . $this->master;
                }

                $losts = array_diff($this->slaves, $this->asyncCheckResult['slaves']);
                if ($losts) {
                    $this->slaves = $this->asyncCheckResult['slaves'];
                    echo RedisProxyFactory::getLogTitle() . 'slave nodes change to ( ' . implode(',',
                            $this->slaves) . ' ), lost ( ' . implode(',', $losts) . ' )';
                }

                $adds = array_diff($this->asyncCheckResult['slaves'], $this->slaves);
                if ($adds) {
                    $this->slaves = $this->asyncCheckResult['slaves'];
                    echo RedisProxyFactory::getLogTitle() . 'slave nodes change to ( ' . implode(',',
                            $this->slaves) . ' ), add ( ' . implode(',', $adds) . ' )';
                }

                foreach ($this->asyncCheckResult['pools'] as $pool => $client) {
                    $client->close();
                    unset($this->asyncCheckResult['pools'][$pool]);
                }
            });
        } catch (SwooleException $e) {
            echo RedisProxyFactory::getLogTitle() . $e->getMessage();
            return false;
        }
    }
}
