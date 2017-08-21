<?php
/**
 * 协程环境Redis实现的分布式锁
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Memory;

use Exception;
use PG\MSF\DataBase\RedisAsynPool;

class Lock
{
    const LOCK_PREFIX = '@PGLock.';

    /**
     * @var int|string 锁的标识
     */
    protected $lockId;

    /**
     * @var RedisAsynPool Redis连接池
     */
    protected $redisPool;

    /**
     * Lock constructor.
     *
     * @param $lockId
     * @param null $redisPoolName
     */
    public function __construct($lockId, $redisPoolName = null)
    {
        $this->lockId = $lockId;
        if (empty($redisPoolName)) {
            $this->redisPool = getInstance()->redisPool;
        } else {
            $this->redisPool = getInstance()->getAsynPool($redisPoolName);
        }
    }

    /**
     * 获得锁可以设置超时时间单位ms,返回使用锁的次数
     *
     * @param int $maxTime
     * @return mixed
     * @throws Exception
     */
    public function goLock($maxTime = 5000)
    {
        $count = 0;
        do {
            $isLock = yield $this->redisPool->getCoroutine()->setnx(self::LOCK_PREFIX . $this->lockId, 0);
            if ($maxTime > 0 && $count >= $maxTime) {
                break;
            }
            $count++;
        } while (!$isLock);
        if (!$isLock) {
            throw new Exception("lock[$this->lockId] time out!");
        }
        return true;
    }

    /**
     * 尝试获得锁
     *
     * @return int
     */
    public function goTryLock()
    {
        $result = yield $this->redisPool->getCoroutine()->setnx(self::LOCK_PREFIX . $this->lockId, 1);
        return $result;
    }

    /**
     * 解锁
     *
     * @return int
     */
    public function coroutineUnlock()
    {
        $result = yield $this->redisPool->getCoroutine()->del(self::LOCK_PREFIX . $this->lockId);
        return $result;
    }

    /**
     * 获取lock_id
     *
     * @return int|string
     */
    public function getLockId()
    {
        return $this->lockId;
    }
}
