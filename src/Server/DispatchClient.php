<?php
/**
 * SwooleDispatchClient
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server;

use Noodlehaus\Exception;
use PG\MSF\Server\DataBase\{
    AsynPoolManager, RedisAsynPool
};

class DispatchClient extends Server
{
    const SERVER_NAME = 'Dispatch';
    /**
     * server_clients
     * @var array
     */
    protected $serverClients = [];


    /**
     * @var RedisAsynPool
     */
    protected $redisPool;
    /**
     * @var AsynPoolManager
     */
    protected $asnyPoolManager;
    /**
     * 异步进程
     * @var
     */
    protected $poolProcess;

    /**
     * SwooleDispatchClient constructor.
     */
    public function __construct()
    {
        $this->name = self::SERVER_NAME;
        //关闭协程
        $this->needCoroutine = false;
        parent::__construct();
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        $this->socketType = SWOOLE_SOCK_UDP;
        $this->socketName = $this->config['dispatch_server']['socket'];
        $this->port = $this->config['dispatch_server']['port'];
        $this->user = $this->config->get('dispatch_server.set.user', '');
        $this->workerNum = $this->config['dispatch_server']['set']['worker_num'];
    }

    /**
     * 启动
     */
    public function start()
    {
        $this->server = new \swoole_server($this->socketName, $this->port, SWOOLE_PROCESS, $this->socketType);
        $this->server->on('Start', [$this, 'onSwooleStart']);
        $this->server->on('WorkerStart', [$this, 'onSwooleWorkerStart']);
        $this->server->on('connect', [$this, 'onSwooleConnect']);
        $this->server->on('receive', [$this, 'onSwooleReceive']);
        $this->server->on('close', [$this, 'onSwooleClose']);
        $this->server->on('WorkerStop', [$this, 'onSwooleWorkerStop']);
        $this->server->on('Task', [$this, 'onSwooleTask']);
        $this->server->on('Finish', [$this, 'onSwooleFinish']);
        $this->server->on('PipeMessage', [$this, 'onSwoolePipeMessage']);
        $this->server->on('WorkerError', [$this, 'onSwooleWorkerError']);
        $this->server->on('ManagerStart', [$this, 'onSwooleManagerStart']);
        $this->server->on('ManagerStop', [$this, 'onSwooleManagerStop']);
        $this->server->on('Packet', [$this, 'onSwoolePacket']);
        $set = $this->setServerSet();
        $set['daemonize'] = self::$daemonize ? 1 : 0;
        $this->server->set($set);
        $this->beforeSwooleStart();
        $this->server->start();
    }

    /**
     * 设置服务器配置参数
     * @return array
     */
    public function setServerSet()
    {
        $set = $this->config['dispatch_server']['set'];
        $set = array_merge($set, $this->probufSet);
        return $set;
    }

    /**
     * beforeSwooleStart
     */
    public function beforeSwooleStart()
    {
        //创建异步连接池进程
        if ($this->config->get('asyn_process_enable', false)) {//代表启动单独进程进行管理
            $this->poolProcess = new \swoole_process(function ($process) {
                $process->name($this->config['server.process_title'] . '-ASYN');
                $this->asnyPoolManager = new AsynPoolManager($process, $this);
                $this->asnyPoolManager->event_add();
                $this->initAsynPools();
                foreach ($this->asynPools as $pool) {
                    $this->asnyPoolManager->registAsyn($pool);
                }
            }, false, 2);
            $this->server->addProcess($this->poolProcess);
        }
    }

    /**
     * 初始化各种连接池
     */
    public function initAsynPools()
    {
        $this->asynPools = [
            'redisPool' => new RedisAsynPool($this->config, 'dispatch')
        ];
    }

    /**
     * onStart
     * @param $serv
     * @throws Exception
     */
    public function onSwooleStart($serv)
    {
        parent::onSwooleStart($serv);
    }

    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        $this->initAsynPools();
        $this->redisPool = $this->asynPools['redisPool'];
        if (!$serv->taskworker) {
            //注册
            $this->asnyPoolManager = new AsynPoolManager($this->poolProcess, $this);
            if (!$this->config['asyn_process_enable']) {
                $this->asnyPoolManager->noEventAdd();
            }
            foreach ($this->asynPools as $pool) {
                $pool->workerInit($workerId);
                $this->asnyPoolManager->registAsyn($pool);
            }
        }
    }

    /**
     * UDP 消息
     * @param $server
     * @param string $data
     * @param array $client_info
     */
    public function onSwoolePacket($server, $data, $client_info)
    {
        parent::onSwoolePacket($server, $data, $client_info);
        if ($data == $this->config['dispatch_server']['password']) {
            for ($i = 0; $i < $this->workerNum; $i++) {
                if ($i == $server->workerId) {
                    continue;
                }
                $data = $this->packSerevrMessageBody(Marco::ADD_SERVER, $client_info['address']);
                $server->sendMessage($data, $i);
            }
            $this->addServerClient($client_info['address']);
        }
    }

    /**
     * 增加一个服务器连接
     * @param $address
     */
    private function addServerClient($address)
    {
        if (key_exists(ip2long($address), $this->serverClients)) {
            return;
        }
        $client = new \swoole_client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);
        $client->set($this->probufSet);
        $client->on("connect", [$this, 'onClientConnect']);
        $client->on("receive", [$this, 'onClientReceive']);
        $client->on("close", [$this, 'onClientClose']);
        $client->on("error", [$this, 'onClientError']);
        $client->address = $address;
        $client->connect($address, $this->config['server']['dispatch_port']);
        $this->serverClients[ip2long($address)] = $client;
    }

    /**
     * PipeMessage
     * @param $serv
     * @param $from_worker_id
     * @param $message
     */
    public function onSwoolePipeMessage($serv, $from_worker_id, $message)
    {
        parent::onSwoolePipeMessage($serv, $from_worker_id, $message);
        $data = unserialize($message);
        switch ($data['type']) {
            case Marco::ADD_SERVER:
                $address = $data['message'];
                $this->addServerClient($address);
                break;
            case Marco::MSG_TYPR_ASYN:
                $this->asnyPoolManager->distribute($data['message']);
                break;
        }
    }

    /**
     * 连接到服务器
     * @param $cli
     */
    public function onClientConnect($cli)
    {
        print_r("connect\n");
        $usid = ip2long($cli->address);
        $write_data = ['wid' => $this->server->workerId, 'usid' => $usid];
        $data = $this->packSerevrMessageBody(Marco::MSG_TYPE_USID, serialize($write_data));
        $cli->usid = $usid;
        $cli->send($this->encode($data));
        //心跳包
        $heartData = $this->encode($this->packSerevrMessageBody(Marco::MSG_TYPE_HEART, null));
        if (!isset($cli->tick)) {
            $cli->tick = swoole_timer_tick(60000, function () use ($cli, $heartData) {
                $cli->send($heartData);
            });
        }
    }

    /**
     * 服务器发来消息
     * @param $cli
     * @param $clientData
     */
    public function onClientReceive($cli, $clientData)
    {
        $data = substr($clientData, $this->packageLengthTypeLength);
        $unserialize_data = unserialize($data);
        $type = $unserialize_data['type']??'';
        $message = $unserialize_data['message']??'';
        switch ($type) {
            case Marco::MSG_TYPE_SEND_GROUP://发送群消息
                //转换为batch
                $this->redisPool->sMembers(Marco::redis_group_hash_name_prefix . $message['groupId'],
                    function ($uids) use ($message) {
                        if ($uids != null && count($uids) > 0) {
                            $this->redisPool->hMGet(Marco::redis_uid_usid_hash_name, $uids,
                                function ($usids) use ($message) {
                                    $temp_dic = [];
                                    foreach ($usids as $uid => $usid) {
                                        if (!empty($usid)) {
                                            $temp_dic[$usid][] = $uid;
                                        }
                                    }
                                    foreach ($temp_dic as $usid => $uids) {
                                        $client = $this->serverClients[$usid]??null;
                                        if ($client == null) {
                                            continue;
                                        }
                                        $client->send($this->encode($this->packSerevrMessageBody(Marco::MSG_TYPE_SEND_BATCH,
                                            [
                                                'data' => $message['data'],
                                                'uids' => $uids
                                            ])));
                                    }
                                });
                        }
                    });
                break;
            case Marco::MSG_TYPE_SEND_BATCH://发送消息
                $this->redisPool->hMGet(Marco::redis_uid_usid_hash_name, $message['uids'],
                    function ($usids) use ($message) {
                        $temp_dic = [];
                        foreach ($usids as $uid => $usid) {
                            if (!empty($usid)) {
                                $temp_dic[$usid][] = $uid;
                            }
                        }
                        foreach ($temp_dic as $usid => $uids) {
                            $client = $this->serverClients[$usid]??null;
                            if ($client == null) {
                                continue;
                            }
                            $client->send($this->encode($this->packSerevrMessageBody(Marco::MSG_TYPE_SEND_BATCH, [
                                'data' => $message['data'],
                                'uids' => $uids
                            ])));
                        }
                    });
                break;
            case Marco::MSG_TYPE_SEND_ALL://发送广播
                foreach ($this->serverClients as $client) {
                    $client->send($clientData);
                }
                break;
            case Marco::MSG_TYPE_SEND://发送给uid
                $this->redisPool->hGet(Marco::redis_uid_usid_hash_name, $message['uid'],
                    function ($usid) use ($clientData) {
                        if (empty($usid) || !key_exists($usid, $this->serverClients)) {
                            return;
                        }
                        $client = $this->serverClients[$usid];
                        $client->send($clientData);
                    });
                break;
            case Marco::MSG_TYPE_KICK_UID://踢人
                $usid = $message['usid'];
                if (empty($usid) || !key_exists($usid, $this->serverClients)) {
                    return;
                }
                $client = $this->serverClients[$usid];
                $client->send($clientData);
                break;
        }
    }

    /**
     * 服务器断开连接
     * @param $cli
     */
    public function onClientClose($cli)
    {
        print_r("close\n");
        if (isset($cli->tick)) {
            swoole_timer_clear($cli->tick);
        }
        $address = $cli->address;
        unset($this->serverClients[ip2long($cli->address)]);
        unset($cli);
        $this->addServerClient($address);
    }

    /**
     * 服务器连接失败
     * @param $cli
     */
    public function onClientError($cli)
    {
        print_r("error\n");
        if (isset($cli->tick)) {
            swoole_timer_clear($cli->tick);
        }
        unset($this->serverClients[ip2long($cli->address)]);
        unset($cli);
    }
}