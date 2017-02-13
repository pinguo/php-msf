<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\CoreBase;


/**
 * 用于并发选择1个结果，相当于go的select
 * Class SelectCoroutine
 * @package Server\CoreBase
 */
class SelectCoroutine extends CoroutineBase
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

    public static function Select($matchFunc, CoroutineBase ...$coroutines)
    {
        return new SelectCoroutine($matchFunc, $coroutines);
    }

    public function getResult()
    {
        $result = parent::getResult();
        foreach ($this->coroutines as $coroutine) {
            $result = $coroutine->getResult();
            if ($result == CoroutineNull::getInstance()) {
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