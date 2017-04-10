<?php
/**
 * Task 异步任务
 * 在worker中的Task会被构建成TaskProxy。这个实例是单例的，
 * 所以发起task请求时每次都要使用loader给TaskProxy赋值，不能缓存重复使用，以免数据错乱。
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

use PG\Log\PGLog;

class Task extends TaskProxy
{
    /**
     * @var PGLog
     */
    public $PGLog;

    public function __construct()
    {
        parent::__construct();
    }

    public function initialization($taskId, $workerPid, $taskName, $methodName, $context)
    {
        $this->taskId = $taskId;
        getInstance()->tidPidTable->set($this->taskId,
            ['pid' => $workerPid, 'des' => "$taskName::$methodName", 'start_time' => time()]);
        $this->start_run_time = microtime(true);
        if ($context) {
            $this->setContext($context);
            $this->PGLog = null;
            $this->PGLog = clone $this->logger;
            $this->PGLog->logId = $this->getContext()->logId;
            $this->PGLog->accessRecord['beginTime'] = microtime(true);
            $this->PGLog->accessRecord['uri'] = str_replace('\\', '/', '/' . $taskName . '/' . $methodName);
            defined('SYSTEM_NAME') && $this->PGLog->channel = SYSTEM_NAME . '-task';
            $this->PGLog->init();
        }
    }

    public function destroy()
    {
        $this->PGLog && $this->PGLog->appendNoticeLog();
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
     * sendToUid
     * @param $uid
     * @param $data
     */
    protected function sendToUid($uid, $data)
    {
        $data = $this->pack->pack($data);
        getInstance()->sendToUid($uid, $data);
    }

    /**
     * sendToUids
     * @param $uids
     * @param $data
     */
    protected function sendToUids($uids, $data)
    {
        $data = $this->pack->pack($data);
        getInstance()->sendToUids($uids, $data);
    }

    /**
     * sendToAll
     * @param $data
     */
    protected function sendToAll($data)
    {
        $data = $this->pack->pack($data);
        getInstance()->sendToAll($data);
    }

    /**
     * 获取同步redis
     * @return \Redis
     * @throws SwooleException
     */
    protected function getRedis()
    {
        return getInstance()->getRedis();
    }

    //运行完后清理下

    /**
     * 获取同步mysql
     * @return \PG\MSF\Server\DataBase\Miner
     */
    protected function getMysql()
    {
        return getInstance()->getMysql();
    }
}