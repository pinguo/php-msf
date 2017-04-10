<?php

/**
 * @desc: 上下文实体对象
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/17
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Helpers;

use PG\Log\PGLog;
use PG\MSF\Server\{
    CoreBase\Input, CoreBase\Output
};

class Context implements \ArrayAccess
{
    /**
     * @var
     */
    public $useCount;

    /**
     * @var
     */
    public $genTime;

    /**
     * @var string
     */
    public $logId;

    /**
     * @var PGLog
     */
    public $PGLog;

    /**
     * @var Input
     */
    public $input;

    /**
     * @var Output
     */
    public $output;

    /**
     * @var \PG\MSF\Server\Controllers\BaseController
     */
    public $controller;

    public function __sleep()
    {
        return ['logId', 'input'];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }

    /**
     * @param mixed $offset
     * @return null
     */
    public function offsetGet($offset)
    {
        return isset($this->{$offset}) ? $this->{$offset} : null;
    }
}