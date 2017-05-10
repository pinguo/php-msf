<?php

/**
 * @desc: 上下文实体对象
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/17
 * @copyright All rights reserved.
 */

namespace PG\MSF\Helpers;

use PG\Context\AbstractContext;
use PG\Log\PGLog;
use PG\MSF\{
    Base\Input, Base\Output
};

class Context extends AbstractContext
{
    /**
     * @var Input
     */
    public $input;

    /**
     * @var Output
     */
    public $output;

    /**
     * @var \PG\MSF\Controllers\BaseController
     */
    public $controller;

    public function __sleep()
    {
        return ['logId', 'input'];
    }

    public function destroy()
    {
        unset($this->logId);
        unset($this->PGLog);
        unset($this->input);
        unset($this->output);
        unset($this->controller);
    }
}