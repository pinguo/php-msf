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
    public $__sleepTime;

    /**
     * @param int $sleepTime 时间，单位为毫秒
     */
    public function __construct($sleepTime)
    {
        $this->__sleepTime = $sleepTime;
        parent::__construct(0);
        $this->requestId   = $this->getContext()->getLogId();

        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function () {
            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
                return;
            }

            $this->result = true;
            $this->ioBack = true;
            $this->nextRun();
        });
    }

    public function send($callback)
    {
        swoole_timer_after($this->__sleepTime, $callback);
        return $this;
    }
}
