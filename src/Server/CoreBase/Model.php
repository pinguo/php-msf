<?php
/**
 * Model 涉及到数据有关的处理
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

use PG\Log\PGLog;

class Model extends CoreBase
{
    /**
     * @var \PG\MSF\Server\DataBase\RedisAsynPool
     */
    public $redisPool;

    /**
     * @var \PG\MSF\Server\DataBase\MysqlAsynPool
     */
    public $mysqlPool;

    /**
     * @var \PG\MSF\Server\Memory\Pool
     */
    public $objectPool;

    /**
     * @var \PG\MSF\Client\Http\Client
     */
    public $client;

    /**
     * @var \PG\MSF\Client\Tcp\Client
     */
    public $tcpClient;

    /**
     * @var PGLog
     */
    public $PGLog;

    final public function __construct()
    {
        parent::__construct();
        $this->redisPool = AOPFactory::getRedisPoolCoroutine(getInstance()->redisPool->getCoroutine(), $this);
        $this->mysqlPool = getInstance()->mysqlPool;
    }

    /**
     * 当被loader时会调用这个方法进行初始化
     * @param \PG\MSF\Server\Helpers\Context $context
     */
    public function initialization($context)
    {
        $this->setContext($context);
        $this->PGLog = $context->PGLog;
        $this->client = $context->controller->client;
        $this->tcpClient = $context->controller->tcpClient;
        $this->objectPool = $context->controller->objectPool;
    }

    /**
     * 销毁回归对象池
     */
    public function destroy()
    {
        unset($this->PGLog, $this->client->context->PGLog);
        parent::destroy();
        ModelFactory::getInstance()->revertModel($this);
    }

}