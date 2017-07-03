<?php
/**
 * 协程任务
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Base\Exception;
use PG\MSF\Helpers\Context;
use PG\MSF\Controllers\Controller;
use PG\AOP\MI;

class Task
{
    use MI;

    /**
     * 协程任务的迭代器
     *
     * @var \Generator
     */
    protected $routine;

    /**
     * 任务销毁标识
     *
     * @var bool
     */
    public $destroy = false;

    /**
     * 协程嵌套栈
     *
     * @var \SplStack
     */
    protected $stack;

    /**
     * 请求的控制器
     *
     * @var Controller
     */
    protected $controller;

    /**
     * 任务ID
     *
     * @var string
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
     * @return $this
     */
    public function initialization(\Generator $routine, Context &$context, Controller &$controller, callable $callBack = null)
    {
        $this->routine    = $routine;
        $this->context    = $context;
        $this->controller = $controller;
        $this->stack      = new \SplStack();
        $this->id         = $context->getLogId();
        $this->callBack   = $callBack;
        return $this;
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
     * @param \Throwable $exception
     */
    public function setException(\Throwable $exception)
    {
        $this->exception = $exception;
    }

    /**
     * 协程调度
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
                        $value->throwException();
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
                    call_user_func([$this->controller, 'onExceptionHandle'], $runTaskException);
                } else {
                    $this->routine->throw($runTaskException);
                }
            }

            unset($value);
        }
    }

    public function handleTaskTimeout(\Throwable $e, $value)
    {
        if ($value != '') {
            $logValue = '';
            dumpInternal($logValue, $value, 0, false);
            $message = 'Yield ' . $logValue . ' message: ' . $e->getMessage();
        } else {
            $message = 'message: ' . $e->getMessage();
        }

        $runTaskException = new Exception($message, $e->getCode(), $e);
        $this->getContext()->getLog()->warning($message);

        if (!empty($value) && $value instanceof IBase && method_exists($value, 'destroy')) {
            $value->destroy();
        }

        return $runTaskException;
    }

    public function handleTaskException(\Throwable $e, $value)
    {
        if ($value != '') {
            $logValue = '';
            dumpInternal($logValue, $value, 0, false);
            $message = 'Yield ' . $logValue . ' message: ' . $e->getMessage();
        } else {
            $message = $e->getMessage();
        }

        $runTaskException = new Exception($message, $e->getCode(), $e);

        while (!empty($this->stack) && !$this->stack->isEmpty()) {
            $this->routine = $this->stack->pop();
            try {
                $this->routine->throw($runTaskException);
                break;
            } catch (\Exception $e) {
            }
        }

        if (!empty($value) && $value instanceof IBase && method_exists($value, 'destroy')) {
            $value->destroy();
        }

        if (!empty($this->stack) && $this->stack->isEmpty()) {
            return $runTaskException;
        }

        return true;
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

    /**
     * [isFinished 判断该task是否完成]
     * @return boolean [description]
     */
    public function isFinished()
    {
        return (empty($this->stack) || $this->stack->isEmpty()) && !$this->routine->valid();
    }

    public function getRoutine()
    {
        return $this->routine;
    }
}
