<?php
/**
 * 协程任务基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

use PG\MSF\Server\Coroutine\CoroutineTask;

abstract class CoroutineBase implements ICoroutineBase
{
    public static $MAX_TIMERS = 0;
    /**
     * 请求语句
     * @var string
     */
    public $request;
    public $result;

    /**
     * 协程执行的超时时间精确到ms
     * @var int
     */
    public $timeout;

    /**
     * 协程执行请求开始时间
     * @var float
     */
    public $requestTime = 0.0;

    /**
     * 协程执行请求结束时间
     */
    public $responseTime = 0.0;

    /**
     * @var CoroutineTask
     */
    public $coroutine;

    /**
     * @var bool
     */
    public $ioBack = false;

    /**
     * CoroutineBase constructor.
     * @param int $timeout
     */
    public function __construct($timeout = 0)
    {
        if (self::$MAX_TIMERS == 0) {
            self::$MAX_TIMERS = get_instance()->config->get('coroution.timerOut', 1000);
        }

        if ($timeout > 0) {
            $this->timeout = $timeout;
        } else {
            $this->timeout = self::$MAX_TIMERS;
        }

        $this->result = CoroutineNull::getInstance();
        $this->requestTime = microtime(true);
    }

    public abstract function send($callback);

    public function getCoroutine()
    {
        return $this->coroutine;
    }

    public function setCoroutine(CoroutineTask $coroutine)
    {
        $this->coroutine = $coroutine;
        return $this;
    }

    public function getResult()
    {
        if ($this->result !== CoroutineNull::getInstance()) {
            return $this->result;
        }

        if (1000*(microtime(true) - $this->requestTime) > $this->timeout) {
            throw new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
        }

        return $this->result;
    }

    public function nextRun($logId)
    {
        if (empty(get_instance()->coroutine->IOCallBack[$logId])) {
            return true;
        }

        foreach (get_instance()->coroutine->IOCallBack[$logId] as $k => $coroutine) {
            if ($coroutine->ioBack && !empty(get_instance()->coroutine->taskMap[$logId])) {
                unset(get_instance()->coroutine->IOCallBack[$logId][$k]);
                get_instance()->coroutine->schedule(get_instance()->coroutine->taskMap[$logId]);
            } else {
                break;
            }
        }
        return true;
    }
}