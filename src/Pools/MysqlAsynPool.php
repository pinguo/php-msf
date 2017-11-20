<?php
/**
 * mysql异步客户端连接池
 *
 * @author tmtbe
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pools;

use Noodlehaus\Config;
use PG\MSF\Coroutine\Mysql;
use PG\AOP\MI;
use PG\MSF\Helpers\Context;

/**
 * Class MysqlAsynPool
 * @package PG\MSF\Pools
 */
class MysqlAsynPool extends AsynPool
{
    // use property and method insert
    use MI;

    /**
     * 连接池类型名称
     */
    const ASYN_NAME = 'mysql.';

    /**
     * @var Miner SQL Builder
     */
    public $dbQueryBuilder;

    /**
     * @var array 绑定的连接映射表
     */
    public $bindPool;

    /**
     * @var int 连接峰值
     */
    protected $mysqlMaxCount = 0;

    /**
     * @var string 连接池标识
     */
    private $active;

    /**
     * MysqlAsynPool constructor.
     *
     * @param Config $config 配置对象
     * @param string $active 连接池名称
     */
    public function __construct($config, $active)
    {
        parent::__construct($config);
        $this->active         = $active;
        $this->bindPool       = [];
    }

    /**
     * 魔术方法
     *
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $context = array_pop($arguments);
        return $this->getDBQueryBuilder($context)->{$name}(...$arguments);
    }

    /**
     * 获取DB Query Builder
     *
     * @param Context $context 请求上下文对象
     * @return Miner
     */
    public function getDBQueryBuilder(Context $context = null)
    {
        if ($this->dbQueryBuilder == null) {
            $this->dbQueryBuilder            = new Miner();
            $this->dbQueryBuilder->mysqlPool = $this;
        }
        $this->dbQueryBuilder->context = $context;

        return $this->dbQueryBuilder;
    }

    /**
     * 执行MySQL SQL
     *
     * @param array $data 执行的SQL信息
     * @throws Exception
     */
    public function execute($data)
    {
        $client = null;
        $bindId = $data['bind_id'] ?? null;
        if ($bindId != null) {//绑定
            $client = $this->bindPool[$bindId]['client'] ?? null;
            $sql = strtolower($data['sql']);
            if ($sql != 'begin' && $client == null) {
                throw new Exception('error mysql affairs not begin.');
            }
        }
        if ($client == null) {
            if (count($this->pool) == 0) {//代表目前没有可用的连接
                $this->prepareOne();
                $this->commands->push($data);
                return;
            } else {
                $client = $this->pool->shift();
                if ($client->isClose ?? false) {
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
                    //发生错误
                } else {
                    // 事务出错不需要自动回滚，否则会带来新问题，调整为手工回滚（modified by xudianyang）
                    //if (isset($data['bind_id'])) {
                    //    $data['sql'] = 'rollback';
                    //    $this->commands->push($data);
                    //}
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
                //回归连接
                if (((time() - $client->genTime) < 3600)
                    || (($this->mysqlMaxCount + $this->waitConnectNum) <= 30)
                ) {
                    $this->pushToPool($client);
                } else {
                    $client->close();
                    $this->mysqlMaxCount--;
                }
            } else {//事务
                $bindId = $data['bind_id'];
                if ($sql == 'commit' || $sql == 'rollback') {//结束事务
                    $this->freeBind($bindId);
                }
            }
        });
    }

    /**
     * 创建一个Mysql连接
     */
    public function prepareOne()
    {
        $this->reconnect();
    }

    /**
     * 重连或者连接
     *
     * @param \swoole_mysql|null $client MySQL连接对象
     */
    public function reconnect($client = null)
    {
        $this->waitConnectNum++;
        if ($client == null) {
            $client = new \swoole_mysql();
            $client->genTime = time();
        }
        $set = $this->config['mysql'][$this->active];
        $client->connect($set, function ($client, $result) use ($set) {
            $this->waitConnectNum--;
            if (!$result) {
                getInstance()->log->error($client->connect_error . ' with Mysql ' . $set['host'] . ':' . $set['port']);
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
     *
     * @param int $bindId bind ID
     * @param Context $context 请求上下文对象
     */
    public function freeBind($bindId, Context $context = null)
    {
        $client = $this->bindPool[$bindId]['client'];
        if ($client != null) {
            $this->pushToPool($client);
        }
        $this->bindPool[$bindId] = null;
        unset($this->bindPool[$bindId]);
    }

    /**
     * 断开链接
     *
     * @param \swoole_mysql $client MySQL连接对象
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

    /**
     * 开启一个同步事务
     *
     * @param Context $context 请求上下文对象
     * @return $this
     */
    public function begin(Context $context = null)
    {
        $this->getDBQueryBuilder($context)->go(null, 'begin');

        return $this;
    }

    /**
     * 获取绑定值
     *
     * @param Context $context 请求上下文对象
     * @return string
     */
    public function bind(Context $context)
    {
        if (!isset($context->__UBID)) {
            $context->__UBID = 0;
        }
        $context->__UBID++;

        return spl_object_hash($context) . $context->__UBID;
    }

    /**
     * 执行一个sql语句
     *
     * @param Context $context 请求上下文对象
     * @param callable $callback 执行完成后的回调函数
     * @param int|null $bindId 绑定ID
     * @param string|null $sql SQL语句
     * @throws Exception
     */
    public function query($callback, $bindId = null, $sql = null, Context $context = null)
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
     *
     * @param Context $context 请求上下文对象
     * @return MySql
     */
    public function goBegin(Context $context = null)
    {
        $id = $this->bind($context);
        return $this->getDBQueryBuilder($context)->go($id, 'begin');
    }

    /**
     * 提交一个同步事务
     *
     * @param Context $context 请求上下文对象
     * @return $this
     */
    public function commit(Context $context = null)
    {
        $this->getDBQueryBuilder($context)->go(null, 'commit');
        return $this;
    }

    /**
     * 协程Commit
     *
     * @param Context $context 请求上下文对象
     * @param int $id 绑定ID
     * @return MySql
     */
    public function goCommit($id, Context $context = null)
    {
        return $this->getDBQueryBuilder($context)->go($id, 'commit');
    }

    /**
     * 回滚
     *
     * @param Context $context 请求上下文对象
     * @return $this
     */
    public function rollback(Context $context = null)
    {
        $this->getDBQueryBuilder($context)->go(null, 'rollback');
        return $this;
    }

    /**
     * 协程Rollback
     *
     * @param Context $context 请求上下文对象
     * @param int $id 绑定ID
     * @return MySql
     */
    public function goRollback($id, Context $context = null)
    {
        return $this->getDBQueryBuilder($context)->go($id, 'rollback');
    }

    /**
     * 获取同步
     *
     * @param Context $context 请求上下文对象
     * @return Miner
     */
    public function getSync(Context $context = null)
    {
        $activeConfig = $this->config['mysql'][$this->active];
        $client = $this->getDBQueryBuilder($context);
        if ($client->getPdoConnection() === null) {
            return $client->pdoConnect($activeConfig);
        } else {
            return $client;
        }
    }
}
