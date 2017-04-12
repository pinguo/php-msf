<?php
/**
 * @desc: Redis proxy
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/10
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Proxy;

use Flexihash\{
    Flexihash
};

class RedisProxy
{
    public static $redisCoroutines = [];
    private static $refreshTicker = false;
    private $proxy;

    public function getProxy()
    {
        //获取之前 设置定时器
        if (!self::$refreshTicker) {
            echo "{$this->getLogTitle()} make ticker\n";
            if (!getInstance()->isTaskWorker()) {
                getInstance()->server->tick('5000', [$this, 'refreshProxy']);
            }
            self::$refreshTicker = true;
        }

        return $this->proxy;
    }



    public function refreshProxy()
    {
        $redisProxyManager = getInstance()->getRedisProxies();
        foreach ($redisProxyManager as $name => &$redisProxy) {
            //分布式
            if ($redisProxy instanceof Flexihash) {
                //原始配置
                $pools = array_keys(getInstance()->config->get("redisProxy.{$name}.pools"));
                //当前proxy配置
                $redisPools = $redisProxy->getAllTargets();

                foreach ($pools as $pool) {
                    try {
                        $redisInfo = getInstance()->config->get('redis.' . $pool);
                        $client = new \swoole_redis;
                        $client->connect($redisInfo['ip'], $redisInfo['port'],
                            function ($client, $result) use ($pool, &$redisProxy, $redisPools, $name) {
                                if ($result === false) {
                                    //不可以使用
                                    if (in_array($pool, $redisPools)) {
                                        $redisProxy->removeTarget($pool);
                                        echo "{$this->getLogTitle()} {$pool} has moved from {$name}\n";
                                    }
                                } else {
                                    $client->set('msf_active_cluster_check', 1,
                                        function ($client, $result) use ($pool, &$redisProxy, $redisPools, $name) {
                                            //可以使用
                                            if ($result === 'OK') {
                                                //当前不在proxy内则加入
                                                if (!in_array($pool, $redisPools)) {
                                                    $redisProxy->addTarget($pool);
                                                    echo "{$this->getLogTitle()} {$pool} has add into {$name}\n";
                                                }
                                            } else {
                                                //不可以使用
                                                if (in_array($pool, $redisPools)) {
                                                    $redisProxy->removeTarget($pool);
                                                    echo "{$this->getLogTitle()} {$pool} has moved from {$name}\n";
                                                }
                                            }
                                        });
                                }
                            });

                    } catch (\Exception $e) {
                        //不可使用
                        if (in_array($pool, $redisPools)) {
                            $redisProxy->removeTarget($pool);
                            echo "{$this->getLogTitle()} {$pool} has moved from {$name}\n";
                        }
                    }
                }

            } else {
                //主从

                //原始配置
                $pools = getInstance()->config->get("redisProxy.{$name}.pools");

                //当前配置
                $nowMaster = $redisProxy->get('master');
                $nowSlaves = $redisProxy->get('slaves');

                //check master
                $newMaster = $nowMaster;
                try {
                    $redisInfo = getInstance()->config->get('redis.' . $nowMaster);
                    $client = new \swoole_redis;
                    $client->connect($redisInfo['ip'], $redisInfo['port'],
                        function ($client, $result) use ($nowMaster) {
                            if ($result === false) {
                                //throw new \RedisException('connect to redis server failed');
                            } else {
                                $client->set('msf_active_master_slave_check', 1,
                                    function ($client, $result) use ($nowMaster) {
                                        //不可以使用
                                        if ($result !== 'OK') {
                                            throw new \RedisException('master-slave master ' . $nowMaster . ' can not write');
                                        }
                                    });
                            }
                        });
                } catch (\Exception $e) {
                    echo "Redis Proxy: {$nowMaster} down in {$name}\n";

                    //检查新的master
                    foreach ($pools as $redisPoolName) {
                        $redisInfo = getInstance()->config->get('redis.' . $redisPoolName);
                        $client = new \swoole_redis;
                        $client->connect($redisInfo['ip'], $redisInfo['port'],
                            function ($client, $result) use ($redisPoolName, &$newMaster, &$redisProxy, $name) {
                                if ($result === false) {
                                    //throw new \RedisException('connect to redis server failed');
                                } else {
                                    $client->set('msf_active_master_slave_check', 1, 30,
                                        function ($client, $result) use (
                                            $redisPoolName,
                                            &$newMaster,
                                            &$redisProxy,
                                            $name
                                        ) {
                                            //可以使用
                                            if ($result) {
                                                $newMaster = $redisPoolName;
                                                $redisProxy->set('master', $newMaster);
                                                echo "{$this->getLogTitle()} New Master: {$newMaster} found for {$name}\n";
                                            }
                                        });
                                }
                            });

                        //找到了新的主节点
                        if ($newMaster != $nowMaster) {
                            break;
                        }
                    }

                    //没有修改成功
                    if ($newMaster === $nowMaster) {
                        echo $this->getLogTitle() . ' No master found in ( ' . implode(',',
                                $pools) . " ) for {$name}, please check! \n";
                    }
                }

                // check slaves
                foreach ($pools as $redisPoolName) {
                    if ($redisPoolName != $newMaster) {
                        try {
                            $redisInfo = getInstance()->config->get('redis.' . $redisPoolName);
                            $client = new \swoole_redis;
                            $client->connect($redisInfo['ip'], $redisInfo['port'],
                                function ($client, $result) use ($redisPoolName, &$redisProxy) {
                                    if ($result === false) {
                                        $redisProxy->unsetSlave($redisPoolName);
                                    } else {
                                        $client->get('msf_active_master_slave_check',
                                            function ($client, $result) use ($redisPoolName, &$redisProxy) {
                                                //可已使用
                                                if ($result == 1) {
                                                    $redisProxy->setSlave($redisPoolName);
                                                } else {
                                                    //不可以使用
                                                    $redisProxy->unsetSlave($redisPoolName);
                                                }
                                            });
                                    }
                                });

                        } catch (\Exception $e) {
                            $redisProxy->unsetSlave($redisPoolName);
                        }
                    }
                }
            }
        }
    }
}
