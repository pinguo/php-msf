<?php
/**
 * Session Redis适配器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Session\Adapters;

use PG\MSF\Base\Core;
use PG\MSF\Session\ISession;

/**
 * Class Redis
 * @package PG\MSF\Session\Adapters
 */
class Redis extends Core implements ISession
{
    /**
     * @var string 存储路径
     */
    private $savePath;
    /**
     * @var string 前缀
     */
    private $sessionName;

    /**
     * redis句柄
     * @var \Redis
     */
    public $redis;

    /**
     * 是否是redis proxy
     * @var bool
     */
    public $isProxy = false;

    public function __construct($isProxy = false)
    {
        $this->isProxy = $isProxy;
    }

    /**
     * 关闭适配器
     * @return mixed
     */
    public function close()
    {
        return true;
    }

    /**
     * 删除当前会话
     * @param string $sessionId 会话id
     * @return mixed
     */
    public function unset(string $sessionId)
    {
        $key = "{$this->sessionName}_{$sessionId}";
        return $this->redis->del($key);
    }

    /**
     * gc
     * 可全量gc或某个会话
     * 由redis的策略控制
     * @param int $maxLifeTime
     * @param string $sessionId
     * @return mixed
     */
    public function gc(int $maxLifeTime, string $sessionId = '')
    {
        return true;
    }

    /**
     * 初始化适配器
     * @param string $savePath redis配置key
     * @param string $name session前缀
     * @return mixed
     */
    public function open(string $savePath, string $name)
    {
        if ($this->isProxy) {
            $this->redis = $this->getRedisProxy($savePath);
        } else {
            $this->redis = $this->getRedisPool($savePath);
        }
        $this->savePath    = $savePath;
        $this->sessionName = $name;
        return true;
    }

    /**
     * 读取session
     * @param string $sessionId 会话id
     * @return mixed
     */
    public function read(string $sessionId)
    {
        $key = "{$this->sessionName}_{$sessionId}";

        return $this->redis->get($key);
    }

    /**
     * 写入session
     * @param string $sessionId 会话id
     * @param string $sessionData 会话内容
     * @return mixed
     */
    public function write(string $sessionId, string $sessionData)
    {
        $key = "{$this->sessionName}_{$sessionId}";

        return $this->redis->set($key, $sessionData, $this->getConfig()->get('session.maxLifeTime', 1440));
    }

    /**
     * 设定session的访问和修改时间
     * @param string $sessionId
     * @return bool
     */
    public function touch(string $sessionId)
    {
        $key = "{$this->sessionName}_{$sessionId}";
        if (yield $this->redis->exists($key)) {
            return $this->redis->expire($key, $this->getConfig()->get('session.maxLifeTime', 1440));
        } else {
            return $this->redis->set($key, '{}',
                $this->getConfig()->get('session.maxLifeTime', 1440));
        }
    }
}
