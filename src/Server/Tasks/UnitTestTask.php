<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-1-4
 * Time: 上午10:46
 */

namespace Server\Tasks;


use Server\CoreBase\Task;
use Server\Test\TestModule;

class UnitTestTask extends Task
{
    public function startTest($dir)
    {
        new TestModule($dir);
    }
}