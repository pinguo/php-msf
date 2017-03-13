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
    protected $lock_id;
    /**
     * @var RedisAsynPool
     */
    protected $redis_pool;

    /**
     * Lock constructor.
     * @param $lock_id
     * @param null $redisPoolName
     */
    public function __construct($lock_id, $redisPoolName = null)
    {
        $this->lock_id = $lock_id;
        if (empty($redisPoolName)) {
            $this->redis_pool = get_instance()->redis_pool;
        } else {
            $this->redis_pool = get_instance()->getAsynPool($redisPoolName);
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
            $isLock = yield $this->redis_pool->getCoroutine()->setnx(self::LOCK_PREFIX . $this->lock_id, 0);
            if ($maxTime > 0 && $count >= $maxTime) {
                break;
            }
            $count++;
        } while (!$isLock);
        if (!$isLock) {
            throw new SwooleException("lock[$this->lock_id] time out!");
        }
        return true;
    }

    /**
     * 尝试获得锁
     * @return int
     */
    public function coroutineTrylock()
    {
        $result = yield $this->redis_pool->getCoroutine()->setnx(self::LOCK_PREFIX . $this->lock_id, 1);
        return $result;
    }

    /**
     * 解锁
     * @return int
     */
    public function coroutineUnlock()
    {
        $result = yield $this->redis_pool->getCoroutine()->del(self::LOCK_PREFIX . $this->lock_id);
        return $result;
    }

    /**
     * 获取lock_id
     * @return int|string
     */
    public function getLockId()
    {
        return $this->lock_id;
    }
}
