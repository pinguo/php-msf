<?php
/**
 * MySql协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Pools\MysqlAsynPool;

/**
 * Class MySql
 * @package PG\MSF\Coroutine
 */
class MySql extends Base
{
    /**
     * @var MysqlAsynPool MySQL连接池对象
     */
    public $mysqlAsynPool;

    /**
     * @var string|null 绑定ID
     */
    public $bindId;

    /**
     * @var string|null 执行的SQL
     */
    public $sql;

    /**
     * MySql constructor.
     *
     * @param MysqlAsynPool $_mysqlAsynPool MySQL连接池对象
     * @param int|null $_bind_id bind ID
     * @param string|null $_sql 执行的SQL
     */
    public function __construct($_mysqlAsynPool, $_bind_id = null, $_sql = null)
    {
        parent::__construct();
        $this->mysqlAsynPool = $_mysqlAsynPool;
        $this->bindId        = $_bind_id;
        $this->sql           = $_sql;
        $this->request       = $this->mysqlAsynPool->getAsynName() . '(' . str_replace("\n", " ", $_sql) . ')';
        $this->requestId     = $this->getContext()->getRequestId();
        $requestId           = $this->requestId;

        $this->getContext()->getLog()->profileStart($this->request);
        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys            = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($result) use ($requestId) {
            if (empty($this->getContext()) || ($requestId != $this->getContext()->getRequestId())) {
                return;
            }

            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
                return;
            }

            $this->getContext()->getLog()->profileEnd($this->request);
            $this->result = $result;
            $this->ioBack = true;
            $this->nextRun();
        });
    }

    /**
     * 发送异步的MySQL请求
     *
     * @param callable $callback 执行SQL后的回调函数
     */
    public function send($callback)
    {
        $this->mysqlAsynPool->query($callback, $this->bindId, $this->sql, $this->getContext());
    }

    /**
     * 获取执行结果
     *
     * @return mixed|null
     * @throws Exception
     */
    public function getResult()
    {
        $result = parent::getResult();
        if (is_array($result) && isset($result['error'])) {
            throw new Exception($result['error']);
        }
        return $result;
    }

    /**
     * 属性不用于序列化
     *
     * @return array
     */
    public function __unsleep()
    {
        return ['mysqlAsynPool'];
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
