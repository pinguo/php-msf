<?php
/**
 * SwooleTestException
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Test;

class SwooleTestException extends \Exception
{
    const ERROR = 0;
    const SKIP = 1;

    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }
}