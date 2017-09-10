<?php
/**
 * 协程任务基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\AOP\MI;

/**
 * Class Base
 * @package PG\MSF\Coroutine
 */
abstract class Base implements IBase
{
    // use property and method insert
    use MI;

    /**
     * @var int 协程运行的最大超时时间
     */
    public static $maxTimeout = 0;

    /**
     * @var string 请求参数
     */
    public $request;

    /**
     * @var mixed IO协程运行的结束
     */
    public $result;

    /**
     * @var int 协程执行的超时时间精确到ms
     */
    public $timeout;

    /**
     * @var float 协程执行请求开始时间
     */
    public $requestTime = 0.0;

    /**
     * @var float 协程执行请求结束时间
     */
    public $responseTime = 0.0;

    /**
     * @var bool IO协程是否返回数据
     */
    public $ioBack = false;

    /**
     * @var int|null ioBack标识
     */
    public $ioBackKey = null;

    /**
     * @var bool 是否发送异步请求后不需要执行回调
     */
    public $isBreak = false;

    /**
     * @var string|null 整个请求标识
     */
    public $requestId = null;

    /**
     * 协程对象初始化（优先执行）
     *
     * @param int $timeout 超时时间，单位毫秒
     */
    public function __construct($timeout = 0)
    {
        if (self::$maxTimeout == 0) {
            self::$maxTimeout = getInstance()->config->get('coroutine.timeout', 30000);
        }

        if ($timeout > 0) {
            $this->timeout = $timeout;
        } else {
            $this->timeout = self::$maxTimeout;
        }

        $this->result      = CNull::getInstance();
        $this->requestTime = microtime(true);
    }

    /**
     * 获取协程执行结果
     *
     * @return mixed|null
     */
    public function getResult()
    {
        if ($this->isTimeout() && !$this->ioBack) {
            return null;
        }

        return $this->result;
    }

    /**
     * 协程超时异常
     *
     * @throws Exception
     */
    public function throwTimeOutException()
    {
        throw new Exception("[coroutine]: Time Out, [class]: " . get_class($this) . ", [Request]: $this->request");
    }

    /**
     * 判断协程是否超时
     *
     * @return bool
     */
    public function isTimeout()
    {
        if (!$this->ioBack && (1000 * (microtime(true) - $this->requestTime) > $this->timeout)) {
            return true;
        }

        return false;
    }

    /**
     * 通知调度器进行下一次迭代
     *
     * @return bool
     */
    public function nextRun()
    {
        if (empty(getInstance()->scheduler->IOCallBack[$this->requestId])) {
            return true;
        }

        foreach (getInstance()->scheduler->IOCallBack[$this->requestId] as $k => $coroutine) {
            if ($coroutine->ioBack && !empty(getInstance()->scheduler->taskMap[$this->requestId])) {
                unset(getInstance()->scheduler->IOCallBack[$this->requestId][$k]);
                getInstance()->scheduler->schedule(getInstance()->scheduler->taskMap[$this->requestId]);
            } else {
                break;
            }
        }

        return true;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        $this->ioBack    = false;
        $this->ioBackKey = null;
        $this->isBreak   = false;
        $this->requestId = null;
    }

    /**
     * 属性不用于序列化
     *
     * @return array
     */
    public function __unsleep()
    {
        return ['context'];
    }

    /**
     * 发送异步请求后不需要执行回调
     *
     * @return bool
     */
    public function break()
    {
        if ($this->requestId && $this->ioBackKey !== null) {
            unset(getInstance()->scheduler->IOCallBack[$this->requestId][$this->ioBackKey]);
            $this->isBreak = true;
        }

        return true;
    }

    /**
     * 手工设置超时时间
     *
     * @param int $timeout 超时时间，单位毫秒
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 发送异步请求
     *
     * @param $callback
     */
    abstract public function send($callback);
}
