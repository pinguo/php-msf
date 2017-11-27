<?php
/**
 * 异步文件系统IO
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

class File extends Base
{
    public $__filename;
    public $__type;
    public $__content;
    public $__flags;

    const READ_FILE  = 1;
    const READ       = 2;
    const WRITE_FILE = 3;
    const WRITE      = 4;

    /**
     * 异步读取文件内容
     * @param string $filename 文件名
     * @return $this
     */
    public function goReadFile(string $filename)
    {
        $this->__filename  = $filename;
        $this->__type      = self::READ_FILE;

        $this->requestId   = $this->getContext()->getRequestId();
        $requestId         = $this->requestId;

        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($filename, $content) use ($requestId) {
            if (empty($this->getContext()) || ($requestId != $this->getContext()->getRequestId())) {
                return;
            }

            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
                return;
            }

            $this->result = $content;
            $this->ioBack = true;
            $this->nextRun();
        });

        return $this;
    }

    /**
     * 异步写文件
     * @param string $filename 文件名
     * @param string $fileContent 内容
     * @param int $flags 选项，FILE_APPEND表示追加
     * @return $this
     */
    public function goWriteFile(string $filename, string $fileContent, int $flags = 0)
    {
        $this->__filename  = $filename;
        $this->__type      = self::WRITE_FILE;
        $this->__content   = $fileContent;
        $this->__flags     = $flags;

        $this->requestId   = $this->getContext()->getRequestId();
        $requestId         = $this->requestId;

        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($filename) use ($requestId) {
            if (empty($this->getContext()) || ($requestId != $this->getContext()->getRequestId())) {
                return;
            }

            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
                return;
            }

            $this->result = true;
            $this->ioBack = true;
            $this->nextRun();
        });

        return $this;
    }


    /**
     * 异步文件IO，操作成功会执行回调，操作失败抛异常
     * @param $callback
     * @return $this
     * @throws Exception
     */
    public function send($callback)
    {
        $result = false;
        if ($this->__type === self::READ_FILE) {
            $result = \Swoole\Async::readFile($this->__filename, $callback);
        }
        if ($this->__type === self::WRITE_FILE) {
            $result = \Swoole\Async::writeFile($this->__filename, $this->__content, $callback, $this->__flags);
        }

        if ($result === false) {
            throw new Exception("{$this->__filename} 无法打开，请检查");
        }

        return $this;
    }
}
