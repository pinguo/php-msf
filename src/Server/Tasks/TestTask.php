<?php
/**
 * TestTask
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Tasks;

use PG\MSF\Server\CoreBase\Task;

class TestTask extends Task
{
    public function testTimer()
    {
        print_r("test timer task\n");
    }

    public function testsend()
    {
        get_instance()->sendToAll(1);
    }

    public function test()
    {
        print_r("test\n");
        return 123;
    }

    public function test_task()
    {
        $testModel = $this->loader->model('TestModel', $this);
        $result = yield $testModel->test_task();
        print_r($result);
    }

    public function testPdo()
    {
        $testModel = $this->loader->model('TestModel',$this);
        yield $testModel->test_pdo();
    }
}