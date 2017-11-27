<?php
/**
 * 配置管理进程类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Process;

use Noodlehaus\Config as Conf;
use PG\MSF\Macro;
use PG\MSF\MSFServer;

/**
 * Class Config
 * @package PG\MSF\Process
 */
class Config extends ProcessBase
{

    /**
     * Redis探测失败次数上限
     */
    const FAILURE_LIMIT = 2;

    /**
     * @var float 上一分钟
     */
    public $lastMinute;

    /**
     * @var array Redis探测retry次数
     */
    protected $redisRetryTimes = [];

    /**
     * Config constructor.
     *
     * @param Conf $config 配置对象
     * @param MSFServer $MSFServer Server运行实例
     */
    public function __construct(Conf $config, MSFServer $MSFServer)
    {
        parent::__construct($config, $MSFServer);
        $this->MSFServer->processType = Macro::PROCESS_CONFIG;
        writeln('Config  Manager: Enabled');
        $this->lastMinute = ceil(time() / 60);
        swoole_timer_tick(3000, [$this, 'checkRedisProxy']);
        swoole_timer_tick(1000, [$this, 'stats']);
    }

    /**
     * 汇总各个Worker的运行状态信息
     */
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
            $workerInfo = @$this->MSFServer->sysCache->get(Macro::SERVER_STATS . $workerId);
            if ($workerInfo) {
                $data['worker']['worker' . $workerId] = $workerInfo;
            } else {
                $data['worker']['worker' . $workerId] = [];
            }
        }

        $lastStats = $this->MSFServer->sysCache->get(Macro::SERVER_STATS);
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
        $this->MSFServer->sysCache->set(Macro::SERVER_STATS, $data);
    }

    /**
     * 检测Redis Proxy状态
     *
     * @return bool
     */
    public function checkRedisProxy()
    {
        $host             = gethostname();
        $redisProxyConfig = $this->config->get('redis_proxy', null);
        $redisConfig      = $this->config->get('redis', null);

        if (empty($redisProxyConfig)) {
            return true;
        }

        foreach ($redisProxyConfig as $proxyName => $proxyConfig) {
            if ($proxyName == 'active') {
                continue;
            }

            //分布式
            if ($proxyConfig['mode'] == Macro::CLUSTER) {
                $pools     = $proxyConfig['pools'];
                $goodPools = [];
                foreach ($pools as $pool => $weight) {
                    try {
                        $redis = new \Redis();
                        $redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 1.5);
                        if(isset($redisConfig[$pool]['password'])){
                            $redis->auth($redisConfig[$pool]['password']);
                        }
                        if(isset($redisConfig[$pool]['select'])){
                            $redis->select($redisConfig[$pool]['select']);
                        }
                        if ($redis->set('msf_active_cluster_check_' . $host, 1, 3)) {
                            $goodPools[$pool] = $weight;
                        }
                    } catch (\Throwable $e) {
                        $error = $redisConfig[$pool]['ip'] . ':' . $redisConfig[$pool]['port'] . " " . $e->getMessage();
                        $this->MSFServer->log->error($error);
                    }
                    $redis->close();

                    //容忍一定次数的错误重试
                    if (isset($goodPools[$pool])) {
                        $this->redisRetryTimes[$pool] = 0;
                    } else {
                        if (!isset($this->redisRetryTimes[$pool])) {
                            $this->redisRetryTimes[$pool] = 0;
                        }

                        $this->redisRetryTimes[$pool]++;
                        if ($this->redisRetryTimes[$pool] < self::FAILURE_LIMIT) {
                            $goodPools[$pool] = $weight;
                        }
                    }
                }
                $this->MSFServer->sysCache->set($proxyName, $goodPools);
            }

            //主从
            if ($proxyConfig['mode'] == Macro::MASTER_SLAVE) {
                $oldConfig = $this->MSFServer->sysCache->get($proxyName);
                $pools     = $proxyConfig['pools'];
                $master    = '';
                $slaves    = [];
                //主
                foreach ($pools as $pool) {
                    if ($master != '') {
                        break;
                    }

                    try {
                        $redis = new \Redis();
                        $redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 1.5);
                        if(isset($redisConfig[$pool]['password'])){
                            $redis->auth($redisConfig[$pool]['password']);
                        }
                        if(isset($redisConfig[$pool]['select'])){
                            $redis->select($redisConfig[$pool]['select']);
                        }
                        if ($redis->set('msf_active_master_slave_check_' . $host, 1, 3)) {
                            $master = $pool;
                        }
                    } catch (\Throwable $e) {
                        //探测主节点时忽略写从的异常信息
                        if (stripos($e->getMessage(), 'readonly') === false) {
                            $error = $redisConfig[$pool]['ip'] . ':' . $redisConfig[$pool]['port'] . ' ' . $e->getMessage();
                            $this->MSFServer->log->error($error);
                        }
                    }
                    $redis->close();
                }

                // 避免主从同步的延迟
                sleep(1);

                //永远不主动踢出master节点
                if ($master != '') {
                    $this->redisRetryTimes[$proxyName] = 0;
                } else {
                    if (!isset($this->redisRetryTimes[$proxyName])) {
                        $this->redisRetryTimes[$proxyName] = 0;
                    }

                    $this->redisRetryTimes[$proxyName]++;
                    $master = $oldConfig['master'] ?? '';
                }

                //从
                if (count($pools) == 1) {
                    $slaves[] = $master;
                    $this->MSFServer->sysCache->set($proxyName, ['master' => $master, 'slaves' => $slaves]);
                    continue;
                }

                foreach ($pools as $pool) {
                    if ($master == $pool) {
                        continue;
                    }

                    try {
                        $redis = new \Redis();
                        $redis->connect($redisConfig[$pool]['ip'], $redisConfig[$pool]['port'], 1.5);
                        if(isset($redisConfig[$pool]['password'])){
                            $redis->auth($redisConfig[$pool]['password']);
                        }
                        if(isset($redisConfig[$pool]['select'])){
                            $redis->select($redisConfig[$pool]['select']);
                        }
                        if ($redis->get('msf_active_master_slave_check_' . $host) == 1) {
                            $slaves[] = $pool;
                        }
                    } catch (\Throwable $e) {
                        $error = $redisConfig[$pool]['ip'] . ':' . $redisConfig[$pool]['port'] . " " . $e->getMessage();
                        $this->MSFServer->log->error($error);
                    }
                    $redis->close();

                    //容忍一定次数的从节点错误
                    if (in_array($pool, $slaves)) {
                        $this->redisRetryTimes[$pool] = 0;
                    } else {
                        if (!isset($this->redisRetryTimes[$pool])) {
                            $this->redisRetryTimes[$pool] = 0;
                        }
                        $this->redisRetryTimes[$pool]++;
                        if ($this->redisRetryTimes[$pool] < self::FAILURE_LIMIT) {
                            $slaves[] = $pool;
                        }
                    }
                }

                $this->MSFServer->sysCache->set($proxyName, ['master' => $master, 'slaves' => $slaves]);
            }
        }

        return true;
    }
}
