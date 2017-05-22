<?php
/**
 * MySql
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Base\Exception;
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
    public $context;

    public function initialization(Context $context, $_mysqlAsynPool, $_bind_id = null, $_sql = null)
    {
        parent::init();
        $this->mysqlAsynPool = $_mysqlAsynPool;
        $this->bindId        = $_bind_id;
        $this->sql           = $_sql;
        $this->request       = 'mysql(' . $_sql . ')';
        $this->context       = $context;
        $logId               = $this->context->getLogId();

        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($result) use ($logId) {
            if (empty(getInstance()->coroutine->taskMap[$logId])) {
                return;
            }

            $this->context->getLog()->profileEnd($this->request);
            $this->result = $result;
            $this->ioBack = true;
            $this->nextRun($logId);
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

    public function destroy()
    {
        parent::destroy();
    }
}
