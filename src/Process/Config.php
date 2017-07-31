<?php
/**
 * @desc: 配置管理进程
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/17
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Process;

use Noodlehaus\Config as Conf;
use PG\MSF\Marco;
use PG\MSF\MSFServer;

class Config
{
    public $config;

    public $MSFServer;

    public $lastMinute;

    protected $redisCheckLock;

    public function __construct(Conf $config, MSFServer $MSFServer)
    {
        echo 'Enable Config Manager: Success', "\n";
        $this->config = $config;
        $this->MSFServer = $MSFServer;
        $this->lastMinute = ceil(time() / 60);
        $this->redisCheckLock = new \swoole_lock();
        swoole_timer_tick(3000, [$this, 'checkRedisProxy']);
        swoole_timer_tick(1000, [$this, 'stats']);
    }

    public function __destruct()
    {
        unset($this->redisCheckLock);
    }

    public function stats()
    {
        $data = [
            'worker' => [
                // worker进程ID
                // 'pid' => 0,
                // 协程统计信息
                // 'coroutine' => [
                // 当前正在处理的请求数
                // 'total' => 0,
                //],
                // 内存使用
                // 'memory' => [
                // 峰值
                // 'peak'  => '',
                // 当前使用
                // 'usage' => '',
                //],
                // 请求信息
                //'request' => [
                // 当前Worker进程收到的请求次数
                //'worker_request_count' => 0,
                //],
            ],
            'tcp' => [
                // 服务器启动的时间
                'start_time' => '',
                // 当前连接的数量
                'connection_num' => 0,
                // 接受了多少个连接
                'accept_count' => 0,
                // 关闭的连接数量
                'close_count' => 0,
                // 当前正在排队的任务数
                'tasking_num' => 0,
                // Server收到的请求次数
                'request_count' => 0,
                // 消息队列中的Task数量
                'task_queue_num' => 0,
                // 消息队列的内存占用字节数
                'task_queue_bytes' => 0,
            ],
        ];

        $workerIds = range(0, $this->MSFServer->server->setting['worker_num'] - 1);
        foreach ($workerIds as $workerId) {
            $workerInfo = $this->MSFServer->sysCache->get(Marco::SERVER_STATS . $workerId);
            if ($workerInfo) {
                $data['worker']['worker' . $workerId] = $workerInfo;
            } else {
                $data['worker']['worker' . $workerId] = [];
            }
        }

        $lastStats = $this->MSFServer->sysCache->get(Marco::SERVER_STATS);
        $data['tcp'] = $this->MSFServer->server->stats();
        $data['running']['qps'] = $data['tcp']['request_count'] - $lastStats['tcp']['request_count'];

        if (!isset($lastStats['running']['last_qpm'])) {
            $data['running']['last_qpm'] = 0;
        } else {
            $data['running']['last_qpm'] = $lastStats['running']['last_qpm'];
        }

        if ($this->lastMinute >= ceil(time() / 60)) {
            if (!empty($lastStats) && !isset($lastStats['running']['qpm'])) {
                $lastStats['running']['qpm'] = 0;
            }

            $data['running']['qpm'] = $lastStats['running']['qpm'] + $data['running']['qps'];
        } else {
            if (!empty($lastStats['running']['qpm'])) {
                $data['running']['last_qpm'] = $lastStats['running']['qpm'];
            }
            $data['running']['qpm'] = $data['running']['qps'];
            $this->lastMinute = ceil(time() / 60);
        }

        unset($data['tcp']['worker_request_count']);
        $this->MSFServer->sysCache->set(Marco::SERVER_STATS, $data);
    }

    public function checkRedisProxy()
    {
        if (!$this->redisCheckLock->lock()) {
            return;
        }

        //todo: retry_timeout， failure_limit

        $redisProxyConfig = $this->config->get('redis_proxy', null);
        $redisConfig = $this->config->get('redis', null);

        if ($redisProxyConfig) {
            foreach ($redisProxyConfig as $proxyName => $proxyConfig) {
                if ($proxyName != 'active') {
                    //分布式
                    if ($proxyConfig['mode'] == Marco::CLUSTER) {
                        $pools = $proxyConfig['pools'];
                        $goodPools = [];
                        foreach ($pools as $pool => $weight) {
                            try {
                                $redis = new \Redis();
                                @$redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 1.5);
                                if ($redis->set('msf_active_cluster_check', 1, 3)) {
                                    $goodPools[$pool] = $weight;
                                }
                            } catch (\Throwable $e) {
                                $error = $redisConfig[$pool]['ip'] . ':' . $redisConfig[$pool]['port'] . " " . $e->getMessage();
                                $this->MSFServer->log->error($error);
                            }
                            $redis->close();
                        }
                        $this->MSFServer->sysCache->set($proxyName, $goodPools);
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
                                @$redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 1.5);
                                if ($redis->set('msf_active_master_slave_check', 1, 3)) {
                                    $master = $pool;
                                }
                            } catch (\Throwable $e) {
                                $error = $redisConfig[$pool]['ip'] . ':' . $redisConfig[$pool]['port'] . " " . $e->getMessage();
                                $this->MSFServer->log->error($error);
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
                                    @$redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 1.5);
                                    if ($redis->get('msf_active_master_slave_check') == 1) {
                                        $slaves[] = $pool;
                                    }
                                } catch (\Throwable $e) {
                                    $error = $redisConfig[$pool]['ip'] . ':' . $redisConfig[$pool]['port'] . " " . $e->getMessage();
                                    $this->MSFServer->log->error($error);
                                }
                                $redis->close();
                            }
                        }

                        $this->MSFServer->sysCache->set($proxyName, ['master' => $master, 'slaves' => $slaves]);
                    }
                }
            }
        }

        $this->redisCheckLock->unlock();
        return true;
    }
}
