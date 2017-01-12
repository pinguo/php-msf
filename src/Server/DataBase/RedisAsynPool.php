<?php
/**
 * redis 异步客户端连接池
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-22
 * Time: 上午10:19
 */

namespace Server\DataBase;


use Server\CoreBase\SwooleException;
use Server\SwooleMarco;

class RedisAsynPool extends AsynPool
{
    const AsynName = 'redis';
    /**
     * 连接
     * @var array
     */
    public $connect;
    protected $redis_max_count = 0;
    private $active;
    private $coroutineRedisHelp;
    public function __construct($connect = null)
    {
        parent::__construct();
        $this->connect = $connect;
        $this->coroutineRedisHelp = new CoroutineRedisHelp($this);
    }

    public function server_init($swoole_server, $asyn_manager)
    {
        parent::server_init($swoole_server, $asyn_manager);
        $this->active = $this->config['redis']['active'];
    }

    /**
     * 映射redis方法
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $callback = array_pop($arguments);
        $data = [
            'name' => $name,
            'arguments' => $arguments
        ];
        $data['token'] = $this->addTokenCallback($callback);
        //写入管道
        $this->asyn_manager->writePipe($this, $data, $this->worker_id);
    }

    /**
     * 协程模式
     * @param $name
     * @param $arg
     * @return RedisCoroutine
     */
    public function coroutineSend($name, ...$arg)
    {
        if (get_instance()->isTaskWorker()) {//如果是task进程自动转换为同步模式
            return call_user_func_array([get_instance()->getRedis(), $name], $arg);
        } else {
            return new RedisCoroutine($this, $name, $arg);
        }
    }

    /**
     * 协程模式 更加便捷
     * @return \Redis
     */
    public function getCoroutine()
    {
        return $this->coroutineRedisHelp;
    }

    /**
     * 执行redis命令
     * @param $data
     */
    public function execute($data)
    {
        if (count($this->pool) == 0) {//代表目前没有可用的连接
            $this->prepareOne();
            $this->commands->push($data);
        } else {
            $client = $this->pool->shift();
            if($client->isClose){
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
                $this->asyn_manager->sendMessageToWorker($this, $data);
                //回归连接
                $this->pushToPool($client);
            };
            $client->__call($data['name'], array_values($arguments));
        }
    }

    /**
     * 准备一个redis
     */
    public function prepareOne()
    {
        if ($this->redis_max_count + $this->waitConnetNum >= $this->config->get('redis.asyn_max_count', 10)) {
            return;
        }
        $this->prepareLock = true;
        $this->reconnect();
    }

    /**
     * 重连或者连接
     * @param null $client
     */
    public function reconnect($client = null)
    {
        $this->waitConnetNum++;
        if ($client == null) {
            $client = new \swoole_redis();
        }
        $callback = function ($client, $result) {
            $this->waitConnetNum--;
            if (!$result) {
                throw new SwooleException($client->errMsg);
            }
            if ($this->config->has('redis.' . $this->active . '.password')) {//存在验证
                $client->auth($this->config['redis'][$this->active]['password'], function ($client, $result) {
                    if (!$result) {
                        $errMsg = $client->errMsg;
                        unset($client);
                        throw new SwooleException($errMsg);
                    }
                    if ($this->config->has('redis.' . $this->active . '.select')) {//存在select
                        $client->select($this->config['redis'][$this->active]['select'], function ($client, $result) {
                            if (!$result) {
                                throw new SwooleException($client->errMsg);
                            }
                            $client->isClose = false;
                            if (!isset($client->client_id)) {
                                $client->client_id = $this->redis_max_count;
                                $this->redis_max_count++;
                            }
                            $this->pushToPool($client);
                        });
                    } else {
                        $client->isClose = false;
                        if (!isset($client->client_id)) {
                            $client->client_id = $this->redis_max_count;
                            $this->redis_max_count++;
                        }
                        $this->pushToPool($client);
                    }
                });
            } else {
                if ($this->config->has('redis.' . $this->active . '.select')) {//存在select
                    $client->select($this->config['redis'][$this->active]['select'], function ($client, $result) {
                        if (!$result) {
                            throw new SwooleException($client->errMsg);
                        }
                        $client->isClose = false;
                        if (!isset($client->client_id)) {
                            $client->client_id = $this->redis_max_count;
                            $this->redis_max_count++;
                        }
                        $this->pushToPool($client);
                    });
                } else {
                    $client->isClose = false;
                    if (!isset($client->client_id)) {
                        $client->client_id = $this->redis_max_count;
                        $this->redis_max_count++;
                    }
                    $this->pushToPool($client);
                }
            }
        };

        if ($this->connect == null) {
            $this->connect = [$this->config['redis'][$this->active]['ip'], $this->config['redis'][$this->active]['port']];
        }
        $client->on('Close',[$this,'onClose']);
        $client->connect($this->connect[0], $this->connect[1], $callback);
    }

    /**
     * 断开链接
     * @param $client
     */
    public function onClose($client){
        $client->isClose = true;
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName;
    }

    /**
     * @return int
     */
    public function getMessageType()
    {
        return SwooleMarco::MSG_TYPE_REDIS_MESSAGE;
    }
}