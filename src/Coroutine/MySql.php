<?php
/**
 * MySql协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use Exception;
use PG\MSF\Pools\MysqlAsynPool;

class MySql extends Base
{
    /**
     * @var MysqlAsynPool
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
     * @param int $_mysqlAsynPool
     * @param null $_bind_id
     * @param null $_sql
     */
    public function __construct($_mysqlAsynPool, $_bind_id = null, $_sql = null)
    {
        parent::__construct();
        $this->mysqlAsynPool = $_mysqlAsynPool;
        $this->bindId        = $_bind_id;
        $this->sql           = $_sql;
        $this->request       = 'mysql(' . $_sql . ')';
        $this->requestId     = $this->getContext()->getLogId();

        getInstance()->coroutine->IOCallBack[$this->requestId][] = $this;
        $keys            = array_keys(getInstance()->coroutine->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($result) {
            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->coroutine->taskMap[$this->requestId])) {
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
     * @param $callback
     */
    public function send($callback)
    {
        $this->mysqlAsynPool->query($callback, $this->bindId, $this->sql);
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
        return ['context', 'mysqlAsynPool'];
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
