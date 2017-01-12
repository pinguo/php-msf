<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-12-30
 * Time: 上午11:49
 */

namespace Server\Test;

use Server\CoreBase\CoreBase;

/**
 * 用 @depends 标注来表达依赖关系
 * 并将所依赖的测试之结果作为参数传入.
 * 测试可以使用多个 @depends 标注。PHPUnit 不会更改测试的运行顺序，因此你需要自行保证某个测试所依赖的所有测试均出现于这个测试之前。
 * 拥有多个 @depends 标注的测试，其第一个参数是第一个生产者提供的基境，第二个参数是第二个生产者提供的基境，以此类推
 * Class TestCase
 * @package Server\CoreBase
 */
abstract class TestCase extends CoreBase
{
    /**
     * @var \Server\DataBase\RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var \Server\DataBase\MysqlAsynPool
     */
    public $mysql_pool;

    public function __construct()
    {
        parent::__construct();
        $this->redis_pool = get_instance()->redis_pool;
        $this->mysql_pool = get_instance()->mysql_pool;
    }

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public abstract function setUpBeforeClass();

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public abstract function tearDownAfterClass();

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public abstract function setUp();

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public abstract function tearDown();

    /**
     * 跳过测试
     * @param $message
     * @throws SwooleTestException
     */
    public function markTestSkipped($message)
    {
        throw new SwooleTestException($message, SwooleTestException::SKIP);
    }

    /**
     * 模拟HTTP请求Controller
     * @param TestRequest $testRequest
     * @return TestHttpCoroutine
     */
    public function coroutineRequestHttpController(TestRequest $testRequest)
    {
        return new TestHttpCoroutine($testRequest);
    }

    /**
     * 模拟TCP请求Controller
     * @param $data
     * @return TestTcpCoroutine
     */
    public function coroutineRequestTcpController($data)
    {
        return new TestTcpCoroutine($data);
    }

    public function assertEquals($expected, $actual, string $message = '')
    {
        if ($expected != $actual) {
            throw new SwooleTestException($message);
        }
    }

    public function assertEmpty($actual, $message = '')
    {
        if (!empty($actual)) {
            throw new SwooleTestException($message);
        }
    }

    public function assertNotEmpty($actual, $message = '')
    {
        if (empty($actual)) {
            throw new SwooleTestException($message);
        }
    }

    public function assertNull($actual, $message = '')
    {
        if ($actual != null) {
            throw new SwooleTestException($message);
        }
    }

    public function assertNotNull($actual, $message = '')
    {
        if ($actual == null) {
            throw new SwooleTestException($message);
        }
    }

    public function assertFalse($actual, $message = '')
    {
        if ($actual) {
            throw new SwooleTestException($message);
        }
    }

    public function assertTrue($actual, $message = '')
    {
        if (!$actual) {
            throw new SwooleTestException($message);
        }
    }

    public function assertCount($expectedCount, $haystack, $message = '')
    {
        if (count($haystack) != $expectedCount) {
            throw new SwooleTestException($message);
        }
    }

    public function assertContains($needle, $haystack, $message = '')
    {
        if (!in_array($needle, $haystack)) {
            throw new SwooleTestException($message);
        }
    }
}