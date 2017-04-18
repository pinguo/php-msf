<?php
/**
 * @desc: 配置管理进程
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/17
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\CoreBase;

use Noodlehaus\Config;
use PG\MSF\Server\Marco;

class ConfigProcess
{
    public $config;

    public function __construct(Config $config)
    {
        echo "启动了configManager\n";
        $this->config = $config;
        swoole_timer_tick(3000, [$this, 'checkRedisProxy']);
    }

    public function checkRedisProxy()
    {
        $lock = new \swoole_lock(SWOOLE_MUTEX, __METHOD__);
        // 抢锁失败
        if (!$lock->trylock()) {
            return false;
        }

        $redisProxyConfig = $this->config->get('redisProxy', null);
        $redisConfig = $this->config->get('redis', null);

        if ($redisProxyConfig) {
            $yac = new \Yac('msf_config_redis_proxy_');
            foreach ($redisProxyConfig as $proxyName => $proxyConfig) {
                if ($proxyName != 'active') {
                    //分布式
                    if ($proxyConfig['mode'] == Marco::CLUSTER) {
                        $pools = $proxyConfig['pools'];
                        $goodPools = [];
                        foreach ($pools as $pool => $weight) {
                            try {
                                $redis = new \Redis();
                                $redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 0.05);
                                if ($redis->set('msf_active_cluster_check', 1, 5)) {
                                    $goodPools[$pool] = $weight;
                                }
                            } catch (\RedisException $e) {

                            }
                            $redis->close();
                        }
                        $yac->set($proxyName, $goodPools);
                    } elseif ($proxyConfig['mode'] == Marco::MASTER_SLAVE) { //主从
                        $pools = $proxyConfig['pools'];
                        $master = '';
                        $slaves = [];
                        //主
                        foreach ($pools as $pool) {
                            if ($master != '') {
                                break;
                            }
                            try {
                                $redis = new \Redis();
                                $redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 0.05);
                                if ($redis->set('msf_active_master_slave_check', 1, 5)) {
                                    $master = $pool;
                                }
                            } catch (\RedisException $e) {

                            }
                            $redis->close();
                        }

                        //从
                        if (count($pools) == 1) {
                            $slaves[] = $master;
                        } else {
                            foreach ($pools as $pool) {
                                if ($master == $pool) {
                                    continue;
                                }
                                try {
                                    $redis = new \Redis();
                                    $redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 0.05);
                                    if ($redis->get('msf_active_master_slave_check') == 1) {
                                        $slaves[] = $pool;
                                    }
                                } catch (\RedisException $e) {

                                }
                                $redis->close();
                            }
                        }

                        $yac->set($proxyName, ['master' => $master, 'slaves' => $slaves]);
                    }
                }
            }
        }

        $lock->unlock();
        unset($lock);
        return true;
    }
}
