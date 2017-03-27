<?php
/**
 * Model 涉及到数据有关的处理
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

use PG\MSF\Server\Helpers\Log\PGLog;

class Model extends CoreBase
{
    /**
     * @var \PG\MSF\Server\DataBase\RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var \PG\MSF\Server\DataBase\MysqlAsynPool
     */
    public $mysql_pool;

    /**
     * @var \PG\MSF\Server\Client\Http\Client
     */
    public $client;

    /**
     * @var \PG\MSF\Server\Client\Tcp\Client
     */
    public $tcpClient;

    /**
     * @var PGLog
     */
    public $PGLog;

    final public function __construct()
    {
        parent::__construct();
        $this->redis_pool = get_instance()->redis_pool;
        $this->mysql_pool = get_instance()->mysql_pool;
        $this->client = clone get_instance()->client;
        $this->tcpClient = clone get_instance()->tcpClient;
    }

    /**
     * 当被loader时会调用这个方法进行初始化
     * @param $context
     */
    public function initialization($context)
    {
        $this->setContext($context);
        if ($this->parent->PGLog) {
            $this->PGLog = $this->parent->PGLog;
            $this->client->context->PGLog = $this->PGLog;
        }
    }

    /**
     * 销毁回归对象池
     */
    public function destroy()
    {
        parent::destroy();
        ModelFactory::getInstance()->revertModel($this);
        unset($this->PGLog, $this->client->context->PGLog);
    }

}