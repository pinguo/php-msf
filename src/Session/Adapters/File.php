<?php
/**
 * Session 文件适配器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Session\Adapters;

use PG\MSF\Base\Core;
use PG\MSF\Session\ISession;

/**
 * Class File
 * @package PG\MSF\Session\Adapters
 */
class File extends Core implements ISession
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
        $file = "{$this->savePath}/{$this->sessionName}_{$sessionId}";
        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    /**
     * gc
     * 可全量gc或某个会话
     * @param int $maxLifeTime
     * @param string $sessionId
     * @return mixed
     */
    public function gc(int $maxLifeTime, string $sessionId = '')
    {
        if ($sessionId) {
            $file = "{$this->savePath}/{$this->sessionName}_{$sessionId}";
            if (file_exists($file) && (filemtime($file) + $maxLifeTime < time())) {
                unlink($file);
            }
            return true;
        }

        foreach (glob("{$this->savePath}/{$this->sessionName}_*") as $file) {
            if (file_exists($file) && (filemtime($file) + $maxLifeTime < time())) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * 初始化适配器
     * @param string $savePath session存储路径
     * @param string $name session前缀
     * @return mixed
     */
    public function open(string $savePath, string $name)
    {
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777);
        }
        $this->savePath = $savePath;
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
        $file = "{$this->savePath}/{$this->sessionName}_{$sessionId}";
        if (!is_file($file)) {
            return false;
        }

        return $this->getObject(\PG\MSF\Coroutine\File::class)->goReadFile($file);
    }

    /**
     * 写入session
     * @param string $sessionId 会话id
     * @param string $sessionData 会话内容
     * @return mixed
     */
    public function write(string $sessionId, string $sessionData)
    {
        return $this->getObject(\PG\MSF\Coroutine\File::class)->goWriteFile(
            "{$this->savePath}/{$this->sessionName}_{$sessionId}",
            $sessionData
        );
    }

    /**
     * 设定session的访问和修改时间
     * @param string $sessionId
     * @return bool
     */
    public function touch(string $sessionId)
    {
        $file = "{$this->savePath}/{$this->sessionName}_{$sessionId}";
        if (file_exists($file)) {
            return touch($file, time());
        } else {
            return $this->getObject(\PG\MSF\Coroutine\File::class)->goWriteFile(
                "{$this->savePath}/{$this->sessionName}_{$sessionId}",
                '{}'
            );
        }
    }
}
