<?php
/**
 * Session 会话
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Session;

use PG\MSF\Base\Core;
use PG\MSF\Macro;
use PG\MSF\Session\Adapters\File;
use PG\MSF\Session\Adapters\Redis;

/**
 * Class Session
 * @package PG\MSF\Session
 */
class Session extends Core
{
    /**
     * session存储路径 默认为/tmp/msf
     * @var string
     */
    protected $savePath    = '';
    /**
     * session前缀 默认为msf_session
     * @var string
     */
    protected $sessionName = '';
    /**
     * session有效期 默认为1440秒
     * @var int
     */
    protected $maxLifeTime = 0;

    /**
     * session适配器 默认为文件适配器
     * @var ISession
     */
    public $sessionHandler = null;

    public $isOpen = false;

    /**
     * sessionId 当前会话ID
     * @var string
     */
    public $sessionId = '';

    /**
     * Session constructor.
     * 加载配置、加载适配器
     */
    public function __construct()
    {
        $handler = $this->getConfig()->get('session.handler', Macro::SESSION_FILE);
        $this->savePath = $this->getConfig()->get('session.savePath', '/tmp/msf');
        $this->sessionName = $this->getConfig()->get('session.sessionName', 'msf_session');
        $this->maxLifeTime = $this->getConfig()->get('session.maxLifeTime', 1440);

        if ($this->sessionHandler == null) {
            if ($handler == Macro::SESSION_FILE) {
                $this->sessionHandler = $this->getObject(File::class);
            } elseif ($handler == Macro::SESSION_REDIS) {
                $this->sessionHandler = $this->getObject(Redis::class);
            } elseif ($handler == Macro::SESSION_REDIS_PROXY) {
                $this->sessionHandler = $this->getObject(Redis::class, [1]);
            } else {
                throw new Exception('不支持的Session存储类型');
            }
        }

        if ($this->isOpen == false) {
            $this->sessionHandler->open($this->savePath, $this->sessionName);
        }
    }

    /**
     * 初始化当前会话 类似session_start()
     * @return bool
     */
    protected function start()
    {
        //获取上下文内的sessionId
        $this->sessionId = $this->getContext()->getUserDefined('sessionId');
        if ($this->sessionId !== null) {
            return true;
        }

        //获取cookie里的sessionId
        $this->sessionId = $this->getContext()->getInput()->getCookie($this->sessionName);
        if (!$this->sessionId) {
            //新建sessionId
            $this->getContext()->getOutput()->setCookie($this->sessionName, $this->getContext()->getLogId());
            $this->sessionId = $this->getContext()->getLogId();
        } else {
            $this->sessionHandler->gc($this->maxLifeTime, $this->sessionId);
        }

        //设定session的访问和修改时间
        yield $this->sessionHandler->touch($this->sessionId);

        //设置上下文内的sessionId
        $this->getContext()->setUserDefined('sessionId', $this->sessionId);

        return true;
    }

    /**
     * 设置session, 支持批量
     * @param string | array $key 键
     * @param mixed $value 值
     * @return bool
     */
    public function set($key, $value)
    {
        //初始化会话
        yield $this->start();

        $data = yield $this->sessionHandler->read($this->sessionId);
        if (!$data) {
            $data = [];
        } else {
            $data = json_decode($data, true);
        }

        if (is_array($key)) {
            $data = array_merge($data, $key);
        } else {
            $data[$key] = $value;
        }

        return yield $this->sessionHandler->write($this->sessionId, json_encode($data));
    }

    /**
     * 获取session
     * @param string $key 键
     * @param null $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        //初始化会话
        yield $this->start();

        $data = yield $this->sessionHandler->read($this->sessionId);
        if (!$data) {
            return $default;
        }

        $data = json_decode($data, true);
        if (!isset($data[$key])) {
            return $default;
        }

        return $data[$key];
    }

    /**
     * 查询某一个键是否存在
     * @param string $key 键
     * @return bool
     */
    public function has(string $key)
    {
        //初始化会话
        yield $this->start();

        $data = yield $this->sessionHandler->read($this->sessionId);
        if (!$data) {
            return false;
        }

        $data = json_decode($data, true);
        if (!isset($data[$key])) {
            return false;
        }

        return true;
    }

    /**
     * 删除某一个键
     * @param string $key 键
     * @return bool
     */
    public function delete(string $key)
    {
        //初始化会话
        yield $this->start();

        $data = yield $this->sessionHandler->read($this->sessionId);
        if (!$data) {
            return true;
        }

        $data = json_decode($data, true);
        if (!isset($data[$key])) {
            return true;
        }

        unset($data[$key]);
        return yield $this->sessionHandler->write($this->sessionId, json_encode($data));
    }

    /**
     * 删除当前会话
     * @return bool
     */
    public function clear()
    {
        //初始化会话
        yield $this->start();

        return yield $this->sessionHandler->unset($this->sessionId);
    }
}
