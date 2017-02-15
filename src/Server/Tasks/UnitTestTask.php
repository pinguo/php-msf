<?php
/**
 * UnitTestTask
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Tasks;


use PG\MSF\Server\CoreBase\Task;
use PG\MSF\Server\Test\TestModule;

class UnitTestTask extends Task
{
    public function startTest($dir)
    {
        new TestModule($dir);
    }
}