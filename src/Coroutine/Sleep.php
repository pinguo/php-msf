<?php
/**
 * 异步协程sleep
 * 类似sleep，但是异步非阻塞
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

class Sleep extends Base
{
    /**
     * sleep的时间
     * @var int
     */
    public $__sleepTime;

    /**
     * @param int $mSec 时间，单位为毫秒
     * @return $this
     */
    public function goSleep(int $mSec)
    {
        $this->__sleepTime = $mSec;
        $this->requestId   = $this->getContext()->getRequestId();
        $requestId         = $this->requestId;
        $this->setTimeout($mSec + 1000); //协程超时时间要比睡眠时间更长

        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function () use ($requestId) {
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
     * 通过定时器来模拟异步IO
     * @param callable $callback 定时器回调函数
     * @return $this
     */
    public function send($callback)
    {
        swoole_timer_after($this->__sleepTime, $callback);
        return $this;
    }
}
