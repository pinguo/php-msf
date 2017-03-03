<?php
/**
 * 协程任务基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

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
     * 获取的次数，用于判断超时
     * @var int
     */
    public $getCount;

    /**
     * 协程执行的超时时间精确到ms
     * @var int
     */
    public $timeout;

    /**
     * 协程执行的绝对时间
     * @var float
     */
    public $requestTime = 0.0;

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
        $this->getCount = 0;
        $this->requestTime = microtime(true);
    }

    public abstract function send($callback);

    public function getResult()
    {
        $this->getCount++;
        if ((($this->getCount > 1) && ((microtime(true) - $this->requestTime) > $this->timeout))
            || ($this->getCount > $this->timeout)
        ) {
            throw new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
        }
        return $this->result;
    }
}