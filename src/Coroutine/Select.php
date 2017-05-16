<?php
/**
 * 用于并发选择1个结果，相当于go的select
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

class Select extends Base
{

    /**
     * @var array
     */
    public $coroutines;
    public $matchFunc;

    public function __construct($matchFunc, $coroutines)
    {
        parent::__construct();
        $this->coroutines = $coroutines;
        $this->matchFunc = $matchFunc;
    }

    public static function Select($matchFunc, Base ...$coroutines)
    {
        return new Select($matchFunc, $coroutines);
    }

    public function getResult()
    {
        $result = parent::getResult();
        foreach ($this->coroutines as $coroutine) {
            $result = $coroutine->getResult();
            if ($result == CNull::getInstance()) {
                continue;
            }
            if (isset($this->matchFunc)) {
                if (call_user_func($this->matchFunc, $result)) {
                    break;
                }
            } else {
                break;
            }
        }
        return $result;
    }

    public function send($callback)
    {
    }
}
