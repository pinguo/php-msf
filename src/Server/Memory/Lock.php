<?php

/**
 * @desc:  Redis 实现分布式锁 (使用时必须在协程环境中)
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/7
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Memory;

use PG\MSF\Server\{
    CoreBase\SwooleException, DataBase\RedisAsynPool
};

class Lock
{
    const LOCK_PREFIX = '@PGLock.';
    /**
     * 锁的标识
     * @var int|string
     */
    protected $lockId;
    /**
     * @var RedisAsynPool
     */
    protected $redisPool;

    /**
     * Lock constructor.
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
     * @param int $maxTime
     * @return mixed
     * @throws SwooleException
     */
    public function coroutineLock($maxTime = 5000)
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
            throw new SwooleException("lock[$this->lockId] time out!");
        }
        return true;
    }

    /**
     * 尝试获得锁
     * @return int
     */
    public function coroutineTrylock()
    {
        $result = yield $this->redisPool->getCoroutine()->setnx(self::LOCK_PREFIX . $this->lockId, 1);
        return $result;
    }

    /**
     * 解锁
     * @return int
     */
    public function coroutineUnlock()
    {
        $result = yield $this->redisPool->getCoroutine()->del(self::LOCK_PREFIX . $this->lockId);
        return $result;
    }

    /**
     * 获取lock_id
     * @return int|string
     */
    public function getLockId()
    {
        return $this->lockId;
    }
}
