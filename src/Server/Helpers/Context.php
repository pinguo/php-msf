<?php

/**
 * @desc: 上下文实体对象
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/17
 * @copyright All rights reserved.
 */
namespace PG\MSF\Server\Helpers;

use PG\MSF\Server\{
    CoreBase\HttpInput, CoreBase\HttpOutput, Helpers\Log\PGLog
};

class Context
{
    /**
     * @var PGLog
     */
    public $PGLog;

    /**
     * @var HttpInput
     */
    public $httpInput;

    /**
     * @var HttpOutput
     */
    public $httpOutput;

    /**
     * @var \PG\MSF\Server\Controllers\BaseController
     */
    public $controller;

    public function __sleep()
    {
        return ['PGLog', 'httpInput', 'httpOutput'];
    }
}