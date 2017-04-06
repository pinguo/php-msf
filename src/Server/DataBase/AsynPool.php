<?php
/**
 * 异步连接池基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\DataBase;

use Noodlehaus\Config;

abstract class AsynPool implements IAsynPool
{
    const MAX_TOKEN = 655360;
    /**
     * @var Config
     */
    public $config;
    protected $commands;
    protected $pool;
    protected $callBacks;
    protected $workerId;
    protected $server;
    protected $swooleServer;
    //避免爆发连接的锁
    protected $token = 0;
    protected $waitConnetNum = 0;
    /**
     * @var AsynPoolManager
     */
    protected $asynManager;

    public function __construct($config)
    {
        $this->callBacks = new \SplFixedArray(self::MAX_TOKEN);
        $this->commands = new \SplQueue();
        $this->pool = new \SplQueue();
        $this->config = $config;
    }

    public function addTokenCallback($callback)
    {
        $token = $this->token;
        $this->callBacks[$token] = $callback;
        $this->token++;
        if ($this->token >= self::MAX_TOKEN) {
            $this->token = 0;
        }
        return $token;
    }

    /**
     * 分发消息
     * @param $data
     */
    public function distribute($data)
    {
        $callback = $this->callBacks[$data['token']];
        unset($this->callBacks[$data['token']]);
        if ($callback != null) {
            call_user_func($callback, $data['result']);
        }
    }

    /**
     * @param $swooleServer
     * @param $asynManager
     */
    public function serverInit($swooleServer, $asynManager)
    {
        $this->swooleServer = $swooleServer;
        $this->server = $swooleServer->server;
        $this->asynManager = $asynManager;
    }

    /**
     * @param $workerId
     */
    public function workerInit($workerId)
    {
        $this->workerId = $workerId;
    }

    /**
     * @param $client
     */
    public function pushToPool($client)
    {
        $this->pool->push($client);
        if (count($this->commands) > 0) {//有残留的任务
            $command = $this->commands->shift();
            $this->execute($command);
        }
    }

    /**
     * 获取同步
     * @return mixed
     */
    abstract public function getSync();
}