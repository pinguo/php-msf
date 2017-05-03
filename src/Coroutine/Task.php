<?php
/**
 * 协程任务
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Base\SwooleException;

class Task
{
    public $routine;
    public $generatorContext;
    public $destroy = false;
    protected $stack;

    public function __construct(\Generator $routine, GeneratorContext $generatorContext)
    {
        $this->routine = $routine;
        $this->generatorContext = $generatorContext;
        $this->stack = new \SplStack();
    }

    /**
     * 协程调度
     */
    public function run()
    {
        $routine = &$this->routine;
        try {
            if (!$routine) {
                return;
            }
            $value = $routine->current();
            //嵌套的协程
            if ($value instanceof \Generator) {
                $this->generatorContext->addYieldStack($value->key());
                $this->stack->push($routine);
                $routine = $value;
                return;
            }

            if ($value != null && $value instanceof IBase) {
                if ($value->isTimeout()) {
                    try {
                        $value->throwSwooleException();
                    } catch (\Exception $e) {
                        $this->handleTaskTimeout($e, $value);
                    }
                    unset($value);
                    $routine->send(null);
                } else {
                    $result = $value->getResult();
                    if ($result !== CNull::getInstance()) {
                        unset($value);
                        $routine->send($result);
                    }
                }

                while (!$routine->valid() && !$this->stack->isEmpty()) {
                    $result = $routine->getReturn();
                    $this->routine = $this->stack->pop();
                    $this->routine->send($result);
                    $this->generatorContext->popYieldStack();
                }
            } else {
                if ($routine->valid()) {
                    $routine->send($value);
                } else {
                    if (count($this->stack) > 0) {
                        $result = $routine->getReturn();
                        $this->routine = $this->stack->pop();
                        $this->routine->send($result);
                    }
                }
            }
        } catch (\Exception $e) {
            if (empty($value)) {
                $value = "";
            }

            $runTaskException = $this->handleTaskException($e, $value);

            if ($this->generatorContext->getController() != null) {
                call_user_func([$this->generatorContext->getController(), 'onExceptionHandle'], $runTaskException);
            } else {
                $routine->throw($runTaskException);
            }
            unset($value);
        }
    }

    public function handleTaskTimeout(\Exception $e, $value)
    {
        if ($value != '') {
            $logValue = '';
            dumpTaskMessage($logValue, $value, 0);
            $message = 'Yield ' . $logValue . ' message: ' . $e->getMessage();
        } else {
            $message = 'message: ' . $e->getMessage();
        }

        $runTaskException = new Exception($message, $e->getCode(), $e);
        $this->generatorContext->setStackMessage($this->routine->key());
        $this->generatorContext->setErrorFile($e->getFile(), $e->getLine());
        $this->generatorContext->setErrorMessage($message);

        if ($runTaskException instanceof SwooleException) {
            $runTaskException->setShowOther($this->generatorContext->getTraceStack() . "\n" . $e->getTraceAsString(),
                $this->generatorContext->getController());
        }
        $this->generatorContext->getController()->PGLog->warning($message);

        if (!empty($value) && $value instanceof IBase && method_exists($value, 'destroy')) {
            $value->destroy();
        }

        return $runTaskException;
    }

    public function handleTaskException(\Exception $e, $value)
    {
        if ($value != '') {
            $logValue = '';
            dumpTaskMessage($logValue, $value, 0);
            $message = 'Yield ' . $logValue . ' message: ' . $e->getMessage();
        } else {
            $message = 'message: ' . $e->getMessage();
        }

        $runTaskException = new Exception($message, $e->getCode(), $e);
        $this->generatorContext->setErrorFile($e->getFile(), $e->getLine());
        $this->generatorContext->setErrorMessage($message);

        while (!$this->stack->isEmpty()) {
            $this->routine = $this->stack->pop();
            try {
                $this->routine->throw($runTaskException);
                break;
            } catch (\Exception $e) {

            }
        }

        if ($runTaskException instanceof SwooleException) {
            $runTaskException->setShowOther($this->generatorContext->getTraceStack() . "\n" . $e->getTraceAsString(),
                $this->generatorContext->getController());
        }

        if (!empty($value) && $value instanceof IBase && method_exists($value, 'destroy')) {
            $value->destroy();
        }

        return $runTaskException;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        if (!$this->destroy) {
            unset(getInstance()->coroutine->taskMap[$this->generatorContext->getController()->PGLog->logId]);
            unset(getInstance()->coroutine->IOCallBack[$this->generatorContext->getController()->PGLog->logId]);
            unset($this->generatorContext->getController()->PGLog);
            unset($this->generatorContext->getController()->logId);
            $this->generatorContext->destroy();
            unset($this->generatorContext);
            unset($this->stack);
            unset($this->routine);
            $this->destroy = true;
            return true;
        } else {
            return false;
        }
    }

    /**
     * [isFinished 判断该task是否完成]
     * @return boolean [description]
     */
    public function isFinished()
    {
        return !empty($this->stack) && $this->stack->isEmpty() && !$this->routine->valid();
    }

    public function getRoutine()
    {
        return $this->routine;
    }
}
