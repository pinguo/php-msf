<?php
/**
 * 协程任务
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Coroutine;
use PG\MSF\Server\CoreBase\GeneratorContext;
use PG\MSF\Server\CoreBase\ICoroutineBase;
use PG\MSF\Server\CoreBase\CoroutineNull;
use PG\MSF\Server\CoreBase\CoroutineException;
use PG\MSF\Server\CoreBase\SwooleException;

class CoroutineTask
{
    protected $stack;
    public $routine;
    public $generatorContext;
    public $destroy = false;
    public $asyncCallBack = [];

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
        $flag = false;
        try {
            if (!$routine) {
                return;
            }
            $value = $routine->current();
            $flag = true;
            //嵌套的协程
            if ($value instanceof \Generator) {
                $this->generatorContext->addYieldStack($routine->key());
                $this->stack->push($routine);
                $routine = $value;
                return;
            }
            if ($value != null && $value instanceof ICoroutineBase) {
                $result = $value->getResult();
                if ($result !== CoroutineNull::getInstance()) {
                    $routine->send($result);
                }
                //嵌套的协程返回
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
            if ($flag) {
                $this->generatorContext->addYieldStack($routine->key());
            }
            if (empty($value)) {
                $value = "";
            }

            $logValue = "";
            dumpCoroutineTaskMessage($logValue, $value, 0);
            $message = 'yield ' . $logValue . ' message: ' . $e->getMessage();
            $runTaskException = new CoroutineException($message, $e->getCode(), $e);
            $this->generatorContext->setErrorFile($runTaskException->getFile(), $runTaskException->getLine());
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
            if ($this->generatorContext->getController() != null) {
                call_user_func([$this->generatorContext->getController(), 'onExceptionHandle'], $runTaskException);
            } else {
                $routine->throw($runTaskException);
            }

            $this->destroy();
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

    /**
     * 销毁
     */
    public function destroy()
    {
        if (!$this->destroy) {
            unset(get_instance()->coroutine->taskMap[$this->generatorContext->getController()->PGLog->logId]);
            unset(get_instance()->coroutine->IOCallBack[$this->generatorContext->getController()->PGLog->logId]);
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
}
