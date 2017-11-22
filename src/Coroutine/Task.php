<?php
/**
 * 请求的协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use Exception;
use PG\MSF\Controllers\Controller;
use PG\AOP\MI;

/**
 * Class Task
 * @package PG\MSF\Coroutine
 */
class Task
{
    // use property and method insert
    use MI;

    /**
     * @var \Generator 协程任务的迭代器
     */
    protected $routine;

    /**
     * @var bool 任务销毁标识
     */
    public $destroy = false;

    /**
     * @var \SplStack 协程嵌套栈
     */
    protected $stack;

    /**
     * @var Controller 请求的控制器
     */
    protected $controller;

    /**
     * @var int 任务ID
     */
    protected $id;

    /**
     * @var callable|null 迭代完成时执行回调函数
     */
    protected $callBack;

    /**
     * 初始化方法
     *
     * @param \Generator $routine 待调度的迭代器实例
     * @param Controller $controller 当前请求控制器名称
     * @param $callBack callable|null 迭代器执行完成后回调函数
     */
    public function __construct(\Generator $routine, Controller &$controller, callable $callBack = null)
    {
        $this->routine    = $routine;
        $this->controller = $controller;
        $this->stack      = new \SplStack();
        $this->id         = $this->getContext()->getRequestId();
        $this->callBack   = $callBack;
    }

    /**
     * 重置迭代器
     *
     * @param \Generator $routine 迭代器实例
     * @return $this
     */
    public function resetRoutine(\Generator $routine = null)
    {
        $this->routine = null;
        $this->routine = $routine;
        return $this;
    }

    /**
     * 获取callback
     *
     * @return mixed
     */
    public function getCallBack()
    {
        return $this->callBack;
    }

    /**
     * 重置callback
     *
     * @param callable|null $callBack 迭代器执行完成后回调函数
     * @return $this
     */
    public function resetCallBack(callable $callBack = null)
    {
        $this->callBack = null;
        $this->callBack = $callBack;
        return $this;
    }

    /**
     * 获取controller
     *
     * @return Controller
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * 请求的协程调度
     */
    public function run()
    {
        try {
            if (!$this->routine) {
                return;
            }

            $value = $this->routine->current();
            //嵌套的协程
            if ($value instanceof \Generator) {
                $this->stack->push($this->routine);
                $this->routine = $value;
                $value = null;
                return;
            }

            if ($value != null && $value instanceof IBase) {
                if ($value->isTimeout()) {
                    try {
                        $value->throwTimeOutException();
                    } catch (\Exception $e) {
                        $this->handleTaskTimeout($e, $value);
                    }
                    unset($value);
                    $this->routine->send(false);
                } else {
                    $result = $value->getResult();
                    if ($result !== CNull::getInstance()) {
                        $this->routine->send($result);
                    }

                    while (!$this->routine->valid() && !empty($this->stack) && !$this->stack->isEmpty()) {
                        try {
                            $result = $this->routine->getReturn();
                        } catch (\Throwable $e) {
                            // not todo
                            $result = false;
                        }
                        $this->routine = null;
                        $this->routine = $this->stack->pop();
                        $this->routine->send($result);
                    }
                }
            } else {
                if ($this->routine instanceof \Generator && $this->routine->valid()) {
                    $this->routine->send($value);
                } else {
                    try {
                        $result = $this->routine->getReturn();
                    } catch (\Throwable $e) {
                        // not todo
                        $result = false;
                    }

                    if (!empty($this->stack) && !$this->stack->isEmpty()) {
                        $this->routine = null;
                        $this->routine = $this->stack->pop();
                    }
                    $this->routine->send($result);
                }
            }
        } catch (\Throwable $e) {
            if (empty($value)) {
                $value = '';
            }

            $this->handleTaskException($e, $value);
            unset($value);
        }
    }

    /**
     * 处理协程任务的超时
     *
     * @param \Throwable $e 异常实例
     * @param mixed $value 当前迭代的值
     * @return \Throwable
     */
    public function handleTaskTimeout(\Throwable $e, $value)
    {
        if ($value != '') {
            $logValue = '';
            dumpInternal($logValue, $value, 0, false);
            $message = 'Yield ' . $logValue . ' message: ' . $e->getMessage();
        } else {
            $message = 'message: ' . $e->getMessage();
        }

        $this->getContext()->getLog()->warning($message);

        return $e;
    }

    /**
     * 处理协程任务的异常
     *
     * @param \Throwable $e 异常实例
     * @param mixed $value 当前迭代的值
     * @return bool|Exception|\Throwable
     * @throws \Throwable
     */
    public function handleTaskException(\Throwable $userException, $value)
    {
        try {
            if (!empty($this->routine) && $this->routine instanceof \Generator) {
                $this->routine->throw($userException);
            }
        } catch (\Throwable $noCatchUserException) {
            if ($this->stack->isEmpty()) {
                try {
                    throw $noCatchUserException;
                } catch (\Throwable $noCatch) {
                    if ($this->controller) {
                        $this->controller->onExceptionHandle($noCatch);
                    } else {
                        throw $noCatch;
                    }
                }

                return true;
            }

            $noCatchException = null;
            while (!$this->stack->isEmpty()) {
                $this->routine = $this->stack->pop();
                try {
                    $this->routine->throw($noCatchUserException);
                    break;
                } catch (\Throwable $e) {
                    if ($this->stack->isEmpty()) {
                        $noCatchException = $noCatchUserException;
                    }
                }
            }

            if ($this->stack->isEmpty() && $noCatchException !== null && $this->controller) {
                $this->controller->onExceptionHandle($noCatchException);
            }
        }

        return true;
    }

    /**
     * [isFinished 判断该task是否完成]
     * @return boolean [description]
     */
    public function isFinished()
    {
        return (empty($this->stack) || $this->stack->isEmpty()) && !$this->routine->valid();
    }

    /**
     * 获取协程任务当前正在运行的迭代器
     *
     * @return \Generator
     */
    public function getRoutine()
    {
        return $this->routine;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        if (!empty($this->id)) {
            getInstance()->scheduler->taskMap[$this->id] = null;
            unset(getInstance()->scheduler->taskMap[$this->id]);
            getInstance()->scheduler->IOCallBack[$this->id] = null;
            unset(getInstance()->scheduler->IOCallBack[$this->id]);
            $this->stack      = null;
            $this->controller = null;
            $this->id         = null;
            $this->callBack   = null;
        }
    }
}
