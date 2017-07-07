<?php
/**
 * MySql
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use Exception;
use PG\MSF\DataBase\MysqlAsynPool;
use PG\MSF\Helpers\Context;

class MySql extends Base
{
    /**
     * @var MysqlAsynPool
     */
    public $mysqlAsynPool;
    public $bindId;
    public $sql;

    public function initialization($_mysqlAsynPool, $_bind_id = null, $_sql = null)
    {
        parent::init();
        $this->mysqlAsynPool = $_mysqlAsynPool;
        $this->bindId        = $_bind_id;
        $this->sql           = $_sql;
        $this->request       = 'mysql(' . $_sql . ')';
        $this->requestId     = $this->getContext()->getLogId();

        getInstance()->coroutine->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->coroutine->IOCallBack[$this->requestId]);
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

        return $this;
    }

    public function send($callback)
    {
        $this->mysqlAsynPool->query($callback, $this->bindId, $this->sql);
    }

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

    public function destroy()
    {
        parent::destroy();
    }
}
