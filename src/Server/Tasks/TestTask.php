<?php
namespace Server\Tasks;

use Server\CoreBase\Task;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午1:06
 */
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