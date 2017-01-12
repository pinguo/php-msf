<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-1-4
 * Time: 下午3:09
 */

namespace test;

use Server\Test\TestCase;
use Server\Test\TestRequest;

/**
 * 服务器控制器测试用例
 * @package test
 */
class ServerControllerTest extends TestCase
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
     * http controller
     * @return \Generator
     */
    public function testHttpController()
    {
        $testRequest = new TestRequest('/TestController/test');
        $testResponse = yield $this->coroutineRequestHttpController($testRequest);
        $this->assertEquals($testResponse->data, 'helloworld');
    }

    /**
     * tcp controller
     * @return \Generator
     */
    public function testTcpController()
    {
        if ($this->config['server']['pack_tool'] != 'JsonPack') {
            $this->markTestSkipped('协议解包不是JsonPack');
        }
        $data = ['controller_name' => 'TestController', 'method_name' => 'testTcp', 'data' => 'helloWorld'];
        $reusult = yield $this->coroutineRequestTcpController($data);
        $this->assertCount(1, $reusult);
    }
}