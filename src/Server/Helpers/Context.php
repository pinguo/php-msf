<?php

/**
 * @desc: 上下文实体对象
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/17
 * @copyright All rights reserved.
 */
namespace PG\MSF\Server\Helpers;

use PG\MSF\Server\{
    CoreBase\Input, CoreBase\Output, Helpers\Log\PGLog
};

class Context implements \ArrayAccess
{
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

    public function offsetSet($offset, $value) {
        $this->{$offset} = $value;
    }

    public function offsetExists($offset) {
        return isset($this->{$offset});
    }

    public function offsetUnset($offset) {
        unset($this->{$offset});
    }

    public function offsetGet($offset) {
        return isset($this->{$offset}) ? $this->{$offset} : null;
    }
}