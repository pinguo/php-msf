<?php
/**
 * 请求的协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use Exception;
use PG\MSF\Helpers\Context;
use PG\MSF\Controllers\Controller;
use PG\AOP\MI;

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
     * @var string 任务ID
     */
    protected $id;

    /**
     * @var \Throwable
     */
    protected $exception;

    /**
     * @var callable|null
     */
    protected $callBack;

    /**
     * 初始化方法
     *
     * @param \Generator $routine
     * @param Context $context
     * @param Controller $controller
     * @param $callBack callable|null
     */
    public function __construct(\Generator $routine, Context &$context, Controller &$controller, callable $callBack = null)
    {
        $this->routine    = $routine;
        $this->context    = $context;
        $this->controller = $controller;
        $this->stack      = new \SplStack();
        $this->id         = $context->getLogId();
        $this->callBack   = $callBack;
    }

    /**
     * 重置迭代器
     *
     * @param \Generator $routine
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
     * @param callable|null $callBack
     * @return $this
     */
    public function resetCallBack(callable $callBack = null)
    {
        $this->callBack = null;
        $this->callBack = $callBack;
        return $this;
    }

    /**
     * 设置调度时产生的异常
     *
     * @param \Throwable $exception
     */
    public function setException(\Throwable $exception)
    {
        $this->exception = $exception;
    }

    /**
     * 请求的协程调度
     */
    public function run()
    {
        try {
            if ($this->exception) {
                throw $this->exception;
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
                            $result = null;
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
                        $result = null;
                    }

                    if (!empty($this->stack) && !$this->stack->isEmpty()) {
                        $this->routine = null;
                        $this->routine = $this->stack->pop();
                    }
                    $this->routine->send($result);
                }
            }
        } catch (\Throwable $e) {
            $this->exception = null;
            if (empty($value)) {
                $value = '';
            }
            $runTaskException = $this->handleTaskException($e, $value);

            if ($runTaskException instanceof \Throwable) {
                if ($this->controller) {
                    $this->controller->onExceptionHandle($runTaskException);
                } else {
                    $this->routine->throw($runTaskException);
                }
            }

            unset($value);
        }
    }

    /**
     * 处理协程任务的超时
     *
     * @param \Throwable $e
     * @param $value
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

        if (!empty($value) && $value instanceof IBase && method_exists($value, 'destroy')) {
            $value->destroy();
        }

        return $e;
    }

    /**
     * 处理协程任务的异常
     *
     * @param \Throwable $e
     * @param $value
     * @return bool|Exception|\Throwable
     */
    public function handleTaskException(\Throwable $e, $value)
    {
        if ($value != '') {
            $logValue = '';
            dumpInternal($logValue, $value, 0, false);
            $message = 'Yield ' . $logValue . ' message: ' . $e->getMessage();
        } else {
            $message = $e->getMessage();
        }

        while (!empty($this->stack) && !$this->stack->isEmpty()) {
            $this->routine = $this->stack->pop();
            try {
                $this->routine->throw($e);
                break;
            } catch (\Exception $e) {
            }
        }

        if (!empty($value) && $value instanceof IBase && method_exists($value, 'destroy')) {
            $value->destroy();
        }

        if (!empty($this->stack) && $this->stack->isEmpty()) {
            return $e;
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
            getInstance()->coroutine->taskMap[$this->id] = null;
            unset(getInstance()->coroutine->taskMap[$this->id]);
            getInstance()->coroutine->IOCallBack[$this->id] = null;
            unset(getInstance()->coroutine->IOCallBack[$this->id]);
            if (getInstance()::mode == 'console') {
                $this->controller->destroy();
            }
            $this->stack      = null;
            $this->controller = null;
            $this->id         = null;
            $this->callBack   = null;
        }
    }
}
