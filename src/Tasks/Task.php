<?php
/**
 * Task 异步任务
 * 在worker中的Task会被构建成TaskProxy。这个实例是单例的，
 * 所以发起task请求时每次都要使用loader给TaskProxy赋值，不能缓存重复使用，以免数据错乱。
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Tasks;

use PG\MSF\Helpers\Context;
use PG\MSF\Base\AOPFactory;

class Task extends TaskProxy
{
    public function __construct()
    {
        parent::__construct();
    }

    public function initialization($taskId, $workerPid, $taskName, $methodName, $context)
    {
        /**
         * @var Context $context
         */
        $this->taskId = $taskId;
        getInstance()->tidPidTable->set($this->taskId,
            ['pid' => $workerPid, 'des' => "$taskName::$methodName", 'start_time' => time()]);
        $this->start_run_time = microtime(true);
        if ($context) {
            $PGLog = null;
            $PGLog = clone $this->getLogger();
            $PGLog->logId = $context->getLogId();
            $PGLog->accessRecord['beginTime'] = microtime(true);
            $PGLog->accessRecord['uri'] = $context->getInput()->getPathInfo();
            $PGLog->pushLog('task', $taskName);
            $PGLog->pushLog('method', $methodName);
            defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME . '-task';
            $PGLog->init();
            // 构造请求上下文成员
            $context->setLogId($PGLog->logId);
            $context->setLog($PGLog);
            $context->setObjectPool(AOPFactory::getObjectPool(getInstance()->objectPool, $this));
            $this->setContext($context);
        }
    }

    public function destroy()
    {
        $this->getContext()->getLog() && $this->getContext()->getLog()->appendNoticeLog();
        getInstance()->tidPidTable->del($this->taskId);
        parent::destroy();
        $this->taskId = 0;
    }

    /**
     * 检查中断信号返回本Task是否该中断
     * @return bool
     */
    protected function checkInterrupted()
    {
        $interrupted = pcntl_signal_dispatch();
        if ($interrupted == false) {
            return false;
        }
        //表总0获得值代表的是需要中断的id
        $interruptedTaskId = getInstance()->tidPidTable->get(0)['pid'];
        //读取后可以释放锁了
        getInstance()->taskLock->unlock();
        if ($interruptedTaskId == $this->taskId) {
            return true;
        }

        return false;
    }

    /**
     * 获取同步redis
     * @return \Redis
     * @throws Exception
     */
    protected function getRedis()
    {
        return getInstance()->getRedis();
    }

    //运行完后清理下

    /**
     * 获取同步mysql
     * @return \PG\MSF\DataBase\Miner
     */
    protected function getMysql()
    {
        return getInstance()->getMysql();
    }
}
