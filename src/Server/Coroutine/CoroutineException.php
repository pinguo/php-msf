<?php
/**
 * CoroutineException
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Coroutine;

use PG\MSF\Server\CoreBase\SwooleException;

class CoroutineException extends SwooleException
{
    /**
     * @return string
     */
    public function getPreviousMessage()
    {
        return $this->getPrevious()->getMessage();
    }

    /**
     * CoroutineException constructor.
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}