<?php
/**
 * redis 异步客户端连接池
 *
 * @author tmtbe
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pools;

use Noodlehaus\Config;
use PG\MSF\Coroutine\Redis;
use PG\MSF\Helpers\Context;
use PG\MSF\Macro;
use PG\MSF\MSFServer;

/**
 * Class RedisAsynPool
 * @package PG\MSF\Pools
 */
class RedisAsynPool extends AsynPool
{
    /**
     * 连接池类型名称
     */
    const ASYN_NAME = 'redis.';

    /**
     * @var array 连接配置信息
     */
    public $connect;

    /**
     * @var int 连接峰值
     */
    protected $redisMaxCount = 0;

    /**
     * @var string 连接池标识
     */
    private $active;

    /**
     * @var CoroutineRedisProxy 连接池辅助类
     */
    private $coroutineRedisHelp;

    /**
     * @var \Redis 同步Redis客户端
     */
    public $redisClient;

    /**
     * @var string Redis Key前缀
     */
    public $keyPrefix = '';

    /**
     * @var bool 是否需要hash key
     */
    public $hashKey = false;

    /**
     * @var bool 是否启用PHP序列化
     */
    public $phpSerialize = false;

    /**
     * @var bool 是否启用Redis序列化
     */
    public $redisSerialize = false;

    /**
     * RedisAsynPool constructor.
     *
     * @param Config $config 配置对象
     * @param string $active 连接池名称
     * @throws Exception
     */
    public function __construct($config, string $active)
    {
        parent::__construct($config);
        $this->active = $active;

        $config = $this->config->get('redis.' . $this->active, null);
        if (!$config) {
            throw new Exception("config redis.$active not exists");
        }

        if (!empty($config['hashKey'])) {
            $this->hashKey = $config['hashKey'];
        }
        if (!empty($config['redisSerialize'])) {
            $this->redisSerialize = $config['redisSerialize'];
        }
        if (!empty($config['phpSerialize'])) {
            $this->phpSerialize = $config['phpSerialize'];
        }
        if (!empty($config['keyPrefix'])) {
            $this->keyPrefix = $config['keyPrefix'];
        }

        $this->coroutineRedisHelp = new CoroutineRedisProxy($this);
    }

    /**
     * 初始化
     *
     * @param MSFServer $swooleServer Server实例
     * @param AsynPoolManager $asynManager 异步连接池管理器
     * @return $this
     */
    public function serverInit($swooleServer, $asynManager)
    {
        parent::serverInit($swooleServer, $asynManager);
        return $this;
    }

    /**
     * __call魔术方法，映射redis方法
     *
     * @param string $name Redis指令
     * @param array $arguments Redis指令参数
     */
    public function __call(string $name, $arguments)
    {
        $callback = array_pop($arguments);
        $data = [
            'name' => $name,
            'arguments' => $arguments
        ];
        $data['token'] = $this->addTokenCallback($callback);
        //写入管道
        $this->asynManager->writePipe($this, $data, $this->workerId);
    }

    /**
     * 协程模式
     *
     * @param Context $context 请求上下文对象
     * @param string $name Redis指令
     * @param array $arg Redis指令参数
     * @return mixed|Redis
     * @throws Exception
     */
    public function go($context, $name, ...$arg)
    {
        if (getInstance()->processType == Macro::PROCESS_TASKER) {//如果是task进程自动转换为同步模式
            return $this->getSync()->$name(...$arg);
        } else {
            return $context->getObjectPool()->get(Redis::class, [$this, $name, $arg]);
        }
    }

    /**
     * 获取同步
     *
     * @return \Redis
     * @throws Exception
     */
    public function getSync()
    {
        if (isset($this->redisClient)) {
            return $this->redisClient;
        }

        //task进程内同步redis连接
        $this->redisClient = new \Redis();
        $conf              = $this->config['redis'][$this->active];
        $addr              = $conf['ip'] . ':' . $conf['port'] . ' ';
        try {
            // 连接
            if (!$this->redisClient->connect($conf['ip'], $conf['port'], 0.05)) {
                throw new Exception($this->redisClient->getLastError());
            }

            // 验证
            if ($this->config->has('redis.' . $this->active . '.password')) {//存在验证
                $addr .= 'auth password failed, message ';
                if (!$this->redisClient->auth($this->config['redis'][$this->active]['password'])) {
                    throw new Exception($this->redisClient->getLastError());
                }
            }

            // select
            if ($this->config->has('redis.' . $this->active . '.select')) {//存在验证
                $addr .= 'select failed, message ';
                if (!$this->redisClient->select($this->config['redis'][$this->active]['select'])) {
                    throw new Exception($this->redisClient->getLastError());
                }
            }
        } catch (Exception $e) {
            throw new Exception($addr . $e->getMessage());
        }

        return $this->redisClient;
    }

    /**
     * 便捷协程模式
     *
     * @return \Redis|coroutineRedisProxy
     */
    public function getCoroutine()
    {
        return $this->coroutineRedisHelp;
    }

    /**
     * 执行Redis命令
     *
     * @param array $data Redis命令相关信息
     */
    public function execute($data)
    {
        if (count($this->pool) == 0) {//代表目前没有可用的连接
            $this->prepareOne();
            $this->commands->push($data);
        } else {
            $client = $this->pool->shift();

            if ($client->isClose) {
                $this->reconnect($client);
                $this->commands->push($data);
                return;
            }

            $arguments = $data['arguments'];
            $dataName = strtolower($data['name']);
            //异步的时候有些命令不存在进行替换
            switch ($dataName) {
                case 'delete':
                    $dataName = $data['name'] = 'del';
                    break;
                case 'lsize':
                    $dataName = $data['name'] = 'llen';
                    break;
                case 'getmultiple':
                    $dataName = $data['name'] = 'mget';
                    break;
                case 'lget':
                    $dataName = $data['name'] = 'lindex';
                    break;
                case 'lgetrange':
                    $dataName = $data['name'] = 'lrange';
                    break;
                case 'lremove':
                    $dataName = $data['name'] = 'lrem';
                    break;
                case 'scontains':
                    $dataName = $data['name'] = 'sismember';
                    break;
                case 'ssize':
                    $dataName = $data['name'] = 'scard';
                    break;
                case 'sgetmembers':
                    $dataName = $data['name'] = 'smembers';
                    break;
                case 'zdelete':
                    $dataName = $data['name'] = 'zrem';
                    break;
                case 'zsize':
                    $dataName = $data['name'] = 'zcard';
                    break;
                case 'zdeleterangebyscore':
                    $dataName = $data['name'] = 'zremrangebyscore';
                    break;
                case 'zunion':
                    $dataName = $data['name'] = 'zunionstore';
                    break;
                case 'zinter':
                    $dataName = $data['name'] = 'zinterstore';
                    break;
            }
            //特别处理下M命令(批量)
            switch ($dataName) {
                case 'lpush':
                case 'srem':
                case 'zrem':
                case 'sadd':
                    $key = $arguments[0];
                    if (is_array($arguments[1])) {
                        $arguments = $arguments[1];
                        array_unshift($arguments, $key);
                    }
                    break;
                case 'del':
                case 'delete':
                    if (is_array($arguments[0])) {
                        $arguments = $arguments[0];
                    }
                    break;
                case 'mset':
                    $harray = $arguments[0];
                    unset($arguments[0]);
                    foreach ($harray as $key => $value) {
                        $arguments[] = $key;
                        $arguments[] = $value;
                    }
                    $data['arguments'] = $arguments;
                    $data['M'] = $harray;
                    break;
                case 'hmset':
                    $harray = $arguments[1];
                    unset($arguments[1]);
                    foreach ($harray as $key => $value) {
                        $arguments[] = $key;
                        $arguments[] = $value;
                    }
                    $data['arguments'] = $arguments;
                    $data['M'] = $harray;
                    break;
                case 'mget':
                    $harray = $arguments[0];
                    unset($arguments[0]);
                    $arguments = array_merge($arguments, $harray);
                    $data['arguments'] = $arguments;
                    $data['M'] = $harray;
                    break;
                case 'hmget':
                    $harray = $arguments[1];
                    unset($arguments[1]);
                    $arguments = array_merge($arguments, $harray);
                    $data['arguments'] = $arguments;
                    $data['M'] = $harray;
                    break;
                case 'lrem'://这里和redis扩展的参数位置有区别
                    $value = $arguments[1];
                    $arguments[1] = $arguments[2];
                    $arguments[2] = $value;
                    break;
                case 'zrevrange':
                case 'zrange':
                    if (count($arguments) == 4) {//存在withscores
                        if ($arguments[3]) {
                            $arguments[3] = 'withscores';
                            $data['withscores'] = true;
                        } else {
                            unset($arguments[3]);
                        }
                    }
                    break;
                case 'zrevrangebyscore'://需要解析参数
                case 'zrangebyscore'://需要解析参数
                    if (count($arguments) == 4) {//存在额外参数
                        $arg = $arguments[3];
                        unset($arguments[3]);
                        $data['withscores'] = $arg['withscores']??false;
                        if ($data['withscores']) {
                            $arguments[] = 'withscores';
                        }
                        if (array_key_exists('limit', $arg)) {//存在limit
                            $arguments[] = 'limit';
                            $arguments[] = $arg['limit'][0];
                            $arguments[] = $arg['limit'][1];
                        }
                    }
                    break;
                case 'zinterstore':
                case 'zunionstore':
                    $arg = $arguments;
                    $argCount = count($arg);
                    unset($arguments);
                    $arguments[] = $arg[0];
                    $arguments[] = count($arg[1]);
                    foreach ($arg[1] as $value) {
                        $arguments[] = $value;
                    }
                    if ($argCount >= 3) {//有WEIGHT
                        $arguments[] = 'WEIGHTS';
                        foreach ($arg[2] as $value) {
                            $arguments[] = $value;
                        }
                    }
                    if ($argCount == 4) {//有AGGREGATE
                        $arguments[] = 'AGGREGATE';
                        $arguments[] = $arg[3];
                    }
                    break;
                case 'sort':
                    $arg = $arguments;
                    $argCount = count($arg);
                    unset($arguments);
                    $arguments[] = $arg[0];
                    if ($argCount == 2) {
                        if (array_key_exists('by', $arg[1])) {
                            $arguments[] = 'by';
                            $arguments[] = $arg[1]['by'];
                        }
                        if (array_key_exists('limit', $arg[1])) {
                            $arguments[] = 'limit';
                            $arguments[] = $arg[1]['limit'][0];
                            $arguments[] = $arg[1]['limit'][1];
                        }
                        if (array_key_exists('get', $arg[1])) {
                            if (is_array($arg[1]['get'])) {
                                foreach ($arg[1]['get'] as $value) {
                                    $arguments[] = 'get';
                                    $arguments[] = $value;
                                }
                            } else {
                                $arguments[] = 'get';
                                $arguments[] = $arg[1];
                            }
                        }
                        if (array_key_exists('sort', $arg[1])) {
                            $arguments[] = $arg[1]['sort'];
                        }
                        if (array_key_exists('alpha', $arg[1])) {
                            $arguments[] = $arg[1]['alpha'];
                        }
                        if (array_key_exists('store', $arg[1])) {
                            $arguments[] = 'store';
                            $arguments[] = $arg[1]['store'];
                        }
                    }
                    break;
            }
            $arguments[] = function ($client, $result) use ($data) {
                switch (strtolower($data['name'])) {
                    case 'hmget':
                        $data['result'] = [];
                        $count = count($result);
                        for ($i = 0; $i < $count; $i++) {
                            $data['result'][$data['M'][$i]] = $result[$i];
                        }
                        break;
                    case 'hgetall':
                        $data['result'] = [];
                        $count = count($result);
                        for ($i = 0; $i < $count; $i = $i + 2) {
                            $data['result'][$result[$i]] = $result[$i + 1];
                        }
                        break;
                    case 'zrevrangebyscore':
                    case 'zrangebyscore':
                    case 'zrevrange':
                    case 'zrange':
                        if ($data['withscores']??false) {
                            $data['result'] = [];
                            $count = count($result);
                            for ($i = 0; $i < $count; $i = $i + 2) {
                                $data['result'][$result[$i]] = $result[$i + 1];
                            }
                        } else {
                            $data['result'] = $result;
                        }
                        break;
                    default:
                        $data['result'] = $result;
                }
                unset($data['M']);
                unset($data['arguments']);
                unset($data['name']);
                //给worker发消息
                $this->asynManager->sendMessageToWorker($this, $data);
                //回归连接
                if (((time() - $client->genTime) < 3600)
                    || (($this->redisMaxCount + $this->waitConnectNum) <= 30)
                ) {
                    $this->pushToPool($client);
                } else {
                    $client->close();
                    $this->redisMaxCount--;
                }
            };
            $client->__call($data['name'], array_values($arguments));
        }
    }

    /**
     * 创建一个Redis连接
     */
    public function prepareOne()
    {
        $this->reconnect();
    }

    /**
     * 重连或者连接
     *
     * @param \swoole_redis|null $client 连接对象
     */
    public function reconnect($client = null)
    {
        $this->waitConnectNum++;
        if ($client == null) {
            $settings = ['timeout' => 1.5];
            //存在密码
            if ($this->config->has('redis.' . $this->active . '.password')) {
                $settings['password'] = $this->config['redis'][$this->active]['password'];
            }
            //存在选库
            if ($this->config->has('redis.' . $this->active . '.select')) {
                $settings['database'] = $this->config['redis'][$this->active]['select'];
            }
            $client = new \swoole_redis($settings);
            $client->genTime = time();
        }

        $this->connect = [$this->config['redis'][$this->active]['ip'], $this->config['redis'][$this->active]['port']];

        $client->on('close', [$this, 'onClose']);
        $client->connect($this->connect[0], $this->connect[1], function ($client, $result) {
            $this->waitConnectNum--;

            if (!$result) {
                getInstance()->log->error($client->errMsg . " with Redis {$this->connect[0]}:{$this->connect[1]}");
                return false;
            }

            $client->isClose = false;
            if (!isset($client->client_id)) {
                $client->client_id = $this->redisMaxCount;
                $this->redisMaxCount++;
            }
            $this->pushToPool($client);
        });
    }

    /**
     * 断开链接
     *
     * @param \swoole_redis $client 连接对象
     */
    public function onClose($client)
    {
        $client->isClose = true;
    }

    /**
     * 返回唯一的连接池名称
     *
     * @return string
     */
    public function getAsynName()
    {
        return self::ASYN_NAME . $this->active;
    }
}
