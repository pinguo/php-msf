<?php
/**
 * mysql异步客户端连接池
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\DataBase;

use PG\MSF\Base\Exception;
use PG\MSF\Coroutine\Mysql;

class MysqlAsynPool extends AsynPool
{
    const AsynName = 'mysql';
    /**
     * @var Miner
     */
    public $dbQueryBuilder;
    /**
     * @var array
     */
    public $bindPool;
    protected $mysqlMaxCount = 0;
    private $active;
    private $mysqlClient;

    public function __construct($config, $active)
    {
        parent::__construct($config);
        $this->active = $active;
        $this->bindPool = [];
        $this->dbQueryBuilder = new Miner();
        $this->dbQueryBuilder->mysqlPool = $this;
    }

    /**
     * 执行mysql命令
     * @param $data
     * @throws Exception
     */
    public function execute($data)
    {
        $client = null;
        $bindId = $data['bind_id']??null;
        if ($bindId != null) {//绑定
            $client = $this->bindPool[$bindId]['client']??null;
            $sql = strtolower($data['sql']);
            if ($sql != 'begin' && $client == null) {
                throw new Exception('error mysql affairs not begin.');
                return;
            }
        }
        if ($client == null) {
            if (count($this->pool) == 0) {//代表目前没有可用的连接
                $this->prepareOne();
                $this->commands->push($data);
                return;
            } else {
                $client = $this->pool->shift();
                if ($client->isClose??false) {
                    $this->reconnect($client);
                    $this->commands->push($data);
                    return;
                }
                if ($bindId != null) {//添加绑定
                    $this->bindPool[$bindId]['client'] = $client;
                }
            }
        }

        $sql = $data['sql'];
        $client->query($sql, function ($client, $result) use ($data) {
            if ($result === false) {
                if ($client->errno == 2006 || $client->errno == 2013) {//断线重连
                    $this->reconnect($client);
                    if (!isset($data['bind_id'])) {//非事务可以重新执行
                        $this->commands->unshift($data);
                    }
                    return;
                } else {//发生错误
                    if (isset($data['bind_id'])) {//事务的话要rollback
                        $data['sql'] = 'rollback';
                        $this->commands->push($data);
                    }
                    //设置错误信息
                    $data['result']['error'] = "[mysql]:" . $client->error . "[sql]:" . $data['sql'];
                }
            }
            $sql = strtolower($data['sql']);
            if ($sql == 'begin') {
                $data['result'] = $data['bind_id'];
            } else {
                $data['result']['client_id'] = $client->client_id;
                $data['result']['result'] = $result;
                $data['result']['affected_rows'] = $client->affected_rows;
                $data['result']['insert_id'] = $client->insert_id;
            }
            //给worker发消息
            $this->asynManager->sendMessageToWorker($this, $data);

            //不是绑定的连接就回归连接
            if (!isset($data['bind_id'])) {
                $this->pushToPool($client);
            } else {//事务
                $bindId = $data['bind_id'];
                if ($sql == 'commit' || $sql == 'rollback') {//结束事务
                    $this->freeBind($bindId);
                }
            }
        });
    }

    /**
     * 准备一个mysql
     */
    public function prepareOne()
    {
        if ($this->mysqlMaxCount + $this->waitConnetNum >= $this->config->get('database.asyn_max_count', 10)) {
            return;
        }
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
            $client = new \swoole_mysql();
        }
        $set = $this->config['database'][$this->active];
        $client->connect($set, function ($client, $result) {
            $this->waitConnetNum--;
            if (!$result) {
                throw new Exception($client->connect_error);
            } else {
                $client->isClose = false;
                if (!isset($client->client_id)) {
                    $client->client_id = $this->mysqlMaxCount;
                    $this->mysqlMaxCount++;
                }
                $this->pushToPool($client);
            }
        });
        $client->on('Close', [$this, 'onClose']);
    }

    /**
     * 释放绑定
     * @param $bindId
     */
    public function freeBind($bindId)
    {
        $client = $this->bindPool[$bindId]['client'];
        if ($client != null) {
            $this->pushToPool($client);
        }
        unset($this->bindPool[$bindId]);
    }

    /**
     * 断开链接
     * @param $client
     */
    public function onClose($client)
    {
        $client->isClose = true;
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName . ":" . $this->active;
    }

    /**
     * 开启一个事务
     * @param $object
     * @param $callback
     * @return string
     */
    public function begin($object, $callback)
    {
        $id = $this->bind($object);
        $this->query($callback, $id, 'begin');
        return $id;
    }

    /**
     * 获取绑定值
     * @param $object
     * @return string
     */
    public function bind($object)
    {
        if (!isset($object->UBID)) {
            $object->UBID = 0;
        }
        $object->UBID++;
        return spl_object_hash($object) . $object->UBID;
    }

    /**
     * 执行一个sql语句
     * @param $callback
     * @param null $bindId
     * @param null $sql
     * @throws Exception
     */
    public function query($callback, $bindId = null, $sql = null)
    {
        if ($sql == null) {
            $sql = $this->dbQueryBuilder->getStatement(false);
            $this->dbQueryBuilder->clear();
        }
        if (empty($sql)) {
            throw new Exception('sql empty');
        }
        $data = [
            'sql' => $sql
        ];
        $data['token'] = $this->addTokenCallback($callback);
        if (!empty($bindId)) {
            $data['bind_id'] = $bindId;
        }
        //写入管道
        $this->asynManager->writePipe($this, $data, $this->workerId);
    }

    /**
     * 开启一个协程事务
     * @param $object
     * @return MySql
     */
    public function coroutineBegin($object)
    {
        $id = $this->bind($object);
        return $this->dbQueryBuilder->coroutineSend($id, 'begin');
    }

    /**
     * 提交一个事务
     * @param $callback
     * @param $id
     */
    public function commit($callback, $id)
    {
        $this->query($callback, $id, 'commit');

    }

    /**
     * 协程Commit
     * @param $id
     * @return MySql
     */
    public function coroutineCommit($id)
    {
        return $this->dbQueryBuilder->coroutineSend($id, 'commit');
    }

    /**
     * 回滚
     * @param $callback
     * @param $id
     */
    public function rollback($callback, $id)
    {
        $this->query($callback, $id, 'rollback');
    }

    /**
     * 协程Rollback
     * @param $id
     * @return MySql
     */
    public function coroutineRollback($id)
    {
        return $this->dbQueryBuilder->coroutineSend($id, 'rollback');
    }

    /**
     * 获取同步
     * @return Miner
     */
    public function getSync()
    {
        if (isset($this->mysqlClient)) {
            return $this->mysqlClient;
        }
        $activeConfig = $this->config['database'][$this->active];
        $this->mysqlClient = new Miner();
        $this->mysqlClient->pdoConnect($activeConfig);
        return $this->mysqlClient;
    }
}