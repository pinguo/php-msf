<?php
/**
 * 协程异常
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Base\Exception as BaseException;

/**
 * Class Exception
 * @package PG\MSF\Coroutine
 */
class Exception extends BaseException
{
    /**
     * 获取前一个异常Message
     *
     * @return string
     */
    public function getPreviousMessage()
    {
        return $this->getPrevious()->getMessage();
    }

    /**
     * Exception constructor.
     *
     * @param string $message 异常信息
     * @param int $code 异常码
     * @param \Exception|null $previous
     */
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
