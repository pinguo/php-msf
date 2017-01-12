<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-1-5
 * Time: 上午10:46
 */

namespace test;


use Server\Test\TestCase;

class ServerUnitTest extends TestCase
{

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function setUpBeforeClass()
    {
        // TODO: Implement setUpBeforeClass() method.
    }

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function tearDownAfterClass()
    {
        // TODO: Implement tearDownAfterClass() method.
    }

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public function setUp()
    {
        // TODO: Implement setUp() method.
    }

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public function tearDown()
    {
        // TODO: Implement tearDown() method.
    }

    /**
     * 依赖的测试
     * @return int
     */
    public function testDepend()
    {
        return 3;
    }

    /**
     * 数据供给器
     * @return array
     */
    public function dataProvider()
    {
        return ['test1' => [1, 2],
            'test2' => [0, 3],
            'test3' => [1, 2],
            'test4' => [0, 3],
            'test5' => [1, 2],
            'test6' => [0, 3]];
    }

    /**
     * 测试数据供给器与依赖
     * @dataProvider dataProvider
     * @depends      testDepend
     * @param   $data1
     * @param   $data2
     */
    public function testDataProvider($data1, $data2, $data3)
    {
        $this->assertEquals($data1 + $data2 + $data3, 6);
    }
}