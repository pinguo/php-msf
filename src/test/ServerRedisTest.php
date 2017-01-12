<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-1-3
 * Time: 上午9:35
 */

namespace test;

use Server\Test\SwooleTestException;
use Server\Test\TestCase;

/**
 * 服务器框架Redis测试用例
 * @needTestTask
 * @package test
 */
class ServerRedisTest extends TestCase
{
    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function setUpBeforeClass()
    {
        yield $this->redis_pool->getCoroutine()->del(["testSet2", "testSet1", "test", "test1", "key1", "testSet", "testlist1"
            , "testlist", "testHash", "testZset", "testZset1", "testZset2", "testuid_level_2", "testuid_level_1", "testuid_name_2", "testuid_name_1"
            , "testuid"]);
    }

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function tearDownAfterClass()
    {
        yield $this->redis_pool->getCoroutine()->del(["testSet2", "testSet1", "test", "test1", "key1", "testSet", "testlist1"
            , "testlist", "testHash", "testZset", "testZset1", "testZset2", "testuid_level_2", "testuid_level_1", "testuid_name_2", "testuid_name_1"
            , "testuid"]);
    }

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public function setUp()
    {

    }

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public function tearDown()
    {

    }

    /**
     * 测试redis连接
     * @throws SwooleTestException
     */
    public function testRedisConnect()
    {
        $value = yield $this->redis_pool->getCoroutine()->ping();
        if (!$value) {
            throw new SwooleTestException('redis连接失败');
        }
    }

    /**
     * Redis set 命令
     * @throws SwooleTestException
     */
    public function testRedsisSet()
    {
        $value = yield $this->redis_pool->getCoroutine()->set('test', 'testRedis');
        if (!$value) {
            throw new SwooleTestException('redis set 失败');
        }
    }

    /**
     * Redis setEx 命令
     * @throws SwooleTestException
     */
    public function testRedsisSetEx()
    {
        $value = yield $this->redis_pool->getCoroutine()->setex('test', 10, 'testRedis');
        if (!$value) {
            throw new SwooleTestException('redis setex 失败');
        }
    }

    /**
     * Redis setNx 命令
     * @throws SwooleTestException
     */
    public function testRedsisSetNx()
    {
        yield $this->redis_pool->getCoroutine()->setnx('test', 'testRedis2');
        $value = yield $this->redis_pool->getCoroutine()->get('test');
        $this->assertEquals($value, 'testRedis', 'redis setnx 失败');
    }

    /**
     * Redis get 命令
     * @throws SwooleTestException
     */
    public function testRedsisGet()
    {
        $value = yield $this->redis_pool->getCoroutine()->get('test');
        if ($value != 'testRedis') {
            throw new SwooleTestException('redis get 失败');
        }
    }

    /**
     * Redis rename 命令
     * @return \Generator
     */
    public function testRedisRename()
    {
        yield $this->redis_pool->getCoroutine()->set('test', 'testRedis');
        yield $this->redis_pool->getCoroutine()->rename('test', 'test1');
        $value = yield $this->redis_pool->getCoroutine()->get('test1');
        $this->assertEquals($value, 'testRedis', 'redis rename 失败');
    }

    /**
     * Redis Mset 命令
     * @throws SwooleTestException
     */
    public function testRedisMset()
    {
        $value = yield $this->redis_pool->getCoroutine()->mset(array('key0' => 'value0', 'key1' => 'value1'));
        if (!$value) {
            throw new SwooleTestException('redis mset 失败');
        }
        $value = yield $this->redis_pool->getCoroutine()->get('key0');
        if ($value != 'value0') {
            throw new SwooleTestException('redis mset 失败');
        }
        $value = yield $this->redis_pool->getCoroutine()->get('key1');
        if ($value != 'value1') {
            throw new SwooleTestException('redis mset 失败');
        }
    }

    /**
     * Redis Mget 命令
     * @throws SwooleTestException
     */
    public function testRedisMget()
    {
        $value = yield $this->redis_pool->getCoroutine()->mget(['key0', 'key1', 'key2']);
        if ($value[0] != 'value0' || $value[1] != 'value1' || $value[2] != null) {
            throw new SwooleTestException('redis mget 失败');
        }
    }

    /**
     * Redis del 命令
     * @return \Generator
     * @throws SwooleTestException
     */
    public function testRedisDel()
    {
        yield $this->redis_pool->getCoroutine()->del('test');
        $value = yield $this->redis_pool->getCoroutine()->get('test');
        if ($value != null) {
            throw new SwooleTestException('redis del 失败');
        }
        yield $this->redis_pool->getCoroutine()->del(array('key0', 'key1'));
        if ($value != null) {
            throw new SwooleTestException('redis del 失败');
        }
        $value = yield $this->redis_pool->getCoroutine()->get('key1');
        if ($value != null) {
            throw new SwooleTestException('redis del 失败');
        }
    }

    /**
     * Redis incr 命令
     * @return \Generator
     * @throws SwooleTestException
     */
    public function testRedisIncr()
    {
        yield $this->redis_pool->getCoroutine()->set('key1', 0);
        yield $this->redis_pool->getCoroutine()->incr('key1');
        $value = yield $this->redis_pool->getCoroutine()->get('key1');
        if ($value != 1) {
            throw new SwooleTestException('redis incr 失败');
        }
    }

    /**
     * Redis incrBy 命令
     * @return \Generator
     * @throws SwooleTestException
     */
    public function testRedisIncrBy()
    {
        yield $this->redis_pool->getCoroutine()->set('key1', 0);
        yield $this->redis_pool->getCoroutine()->incrBy('key1', 10);
        $value = yield $this->redis_pool->getCoroutine()->get('key1');
        if ($value != 10) {
            throw new SwooleTestException('redis incrBy 失败');
        }
    }

    /**
     * Redis decr 命令
     * @return \Generator
     * @throws SwooleTestException
     */
    public function testRedisDecr()
    {
        yield $this->redis_pool->getCoroutine()->set('key1', 1);
        yield $this->redis_pool->getCoroutine()->decr('key1');
        $value = yield $this->redis_pool->getCoroutine()->get('key1');
        if ($value != 0) {
            throw new SwooleTestException('redis decr 失败');
        }
    }

    /**
     * Redis decrBy 命令
     * @return \Generator
     * @throws SwooleTestException
     */
    public function testRedisDecrBy()
    {
        yield $this->redis_pool->getCoroutine()->set('key1', 10);
        yield $this->redis_pool->getCoroutine()->decrBy('key1', 10);
        $value = yield $this->redis_pool->getCoroutine()->get('key1');
        if ($value != 0) {
            throw new SwooleTestException('redis incrBy 失败');
        }
    }

    /**
     * Redis exists 命令
     * @throws SwooleTestException
     */
    public function testRedisExists()
    {
        $value = yield $this->redis_pool->getCoroutine()->exists('key1000');
        if ($value) {
            throw new SwooleTestException('redis exists 失败');
        }
        $value = yield $this->redis_pool->getCoroutine()->exists('key1');
        if (!$value) {
            throw new SwooleTestException('redis exists 失败');
        }
    }

    /**
     * Redis lPush 命令
     * @throws SwooleTestException
     */
    public function testRedisLPush()
    {
        $value = yield $this->redis_pool->getCoroutine()->lpush('testlist', 'test1');
        if (!$value) {
            throw new SwooleTestException('redis lpush 失败');
        }
    }

    /**
     * Redis rPush 命令
     * @throws SwooleTestException
     */
    public function testRedisRPush()
    {
        $value = yield $this->redis_pool->getCoroutine()->rpush('testlist', 'test2');
        if (!$value) {
            throw new SwooleTestException('redis rpush 失败');
        }
    }

    /**
     * Redis lPop 命令
     * @throws SwooleTestException
     */
    public function testRedisLPop()
    {
        $value = yield $this->redis_pool->getCoroutine()->lpop('testlist');
        if ($value != 'test1') {
            throw new SwooleTestException('redis lpop 失败');
        }
    }

    /**
     * Redis rPop 命令
     * @throws SwooleTestException
     */
    public function testRedisRPop()
    {
        $value = yield $this->redis_pool->getCoroutine()->rpop('testlist');
        if ($value != 'test2') {
            throw new SwooleTestException('redis rpop 失败');
        }
    }

    /**
     * Redis lSet 命令
     * @throws SwooleTestException
     */
    public function testRedisLSet()
    {
        yield $this->redis_pool->getCoroutine()->lpush('testlist', 'test1');
        $value = yield $this->redis_pool->getCoroutine()->lset('testlist', 0, 'test0');
        if (!$value) {
            throw new SwooleTestException('redis lSet 失败');
        }
    }

    /**
     * Redis lIndex 命令
     * @throws SwooleTestException
     */
    public function testRedisLIndex()
    {
        $value = yield $this->redis_pool->getCoroutine()->lIndex('testlist', 0);
        if ($value != 'test0') {
            throw new SwooleTestException('redis lIndex 失败');
        }
    }

    /**
     * Redis lLen 命令
     * @throws SwooleTestException
     */
    public function testRedisLLen()
    {
        $value = yield $this->redis_pool->getCoroutine()->lLen('testlist');
        if ($value != 1) {
            throw new SwooleTestException('redis lLen 失败');
        }
    }

    /**
     * Redis lRange 命令
     * @throws SwooleTestException
     */
    public function testRedisLRange()
    {
        yield $this->redis_pool->getCoroutine()->lpush('testlist', 'test3');
        $value = yield $this->redis_pool->getCoroutine()->lRange('testlist', 0, -1);
        if (count($value) != 2) {
            throw new SwooleTestException('redis lRange 失败');
        }
    }

    /**
     * Redis lTrim 命令
     * @throws SwooleTestException
     */
    public function testRedisLTrim()
    {
        yield $this->redis_pool->getCoroutine()->lpush('testlist', 'test4');
        yield $this->redis_pool->getCoroutine()->lpush('testlist', 'test5');
        yield $this->redis_pool->getCoroutine()->lpush('testlist', 'test6');
        $value = yield $this->redis_pool->getCoroutine()->lTrim('testlist', 0, 1);
        if (!$value) {
            throw new SwooleTestException('redis lTrim 失败');
        }
        $value = yield $this->redis_pool->getCoroutine()->lLen('testlist');
        if ($value != 2) {
            throw new SwooleTestException('redis lTrim 失败');
        }
    }

    /**
     * Redis lRem 命令
     * @throws SwooleTestException
     */
    public function testRedisLRem()
    {
        yield $this->redis_pool->getCoroutine()->lpush('testlist', 'testrem');
        yield $this->redis_pool->getCoroutine()->lpush('testlist', 'testrem');
        $value = yield $this->redis_pool->getCoroutine()->lRem('testlist', 'testrem', 0);
        if (!$value) {
            throw new SwooleTestException('redis lRem 失败');
        }
        $value = yield $this->redis_pool->getCoroutine()->lLen('testlist');
        if ($value != 2) {
            throw new SwooleTestException('redis lRem 失败');
        }
    }

    /**
     * Redis lInsert 命令
     * @return \Generator
     * @throws SwooleTestException
     */
    public function testRedisLInsert()
    {
        yield $this->redis_pool->getCoroutine()->lset('testlist', 0, 'testinsert');
        yield $this->redis_pool->getCoroutine()->lInsert('testlist', \Redis::AFTER, 'testinsert', 'testinsert1');
        $value = yield $this->redis_pool->getCoroutine()->lIndex('testlist', '1');
        if ($value != 'testinsert1') {
            throw new SwooleTestException('redis lInsert 失败');
        }
    }

    /**
     * Redis rpoplpush 命令
     * @return \Generator
     * @throws SwooleTestException
     */
    public function testRedisRpoplpush()
    {
        yield $this->redis_pool->getCoroutine()->rpush('testlist', 'testrpoplpush');
        yield $this->redis_pool->getCoroutine()->rpoplpush('testlist', 'testlist1');
        $value = yield $this->redis_pool->getCoroutine()->lIndex('testlist1', '0');
        if ($value != 'testrpoplpush') {
            throw new SwooleTestException('redis rpoplpush 失败');
        }
    }

    /**
     * Redis sAdd 命令
     * @return \Generator
     */
    public function testRedisSAdd()
    {
        $value = yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        $this->assertTrue($value, 'redis sadd 失败');
        $value = yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        $this->assertFalse($value, 'redis sadd 失败');
    }

    /**
     * Redis sIsMember 命令
     * @return \Generator
     */
    public function testRedisSIsMember()
    {
        $value = yield $this->redis_pool->getCoroutine()->sIsMember('testSet', 'index0');
        $this->assertTrue($value, 'redis sIsMember 失败');
    }

    /**
     * Redis sRem 命令
     * @return \Generator
     */
    public function testRedisSRem()
    {
        $value = yield $this->redis_pool->getCoroutine()->sRem('testSet', 'index0');
        $this->assertTrue($value, 'redis sRem 失败');
        $value = yield $this->redis_pool->getCoroutine()->sIsMember('testSet', 'index0');
        $this->assertFalse($value, 'redis sRem 失败');
    }

    /**
     * Redis sCard 命令
     * @return \Generator
     */
    public function testRedisSCard()
    {
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index1');
        $value = yield $this->redis_pool->getCoroutine()->sCard('testSet');
        $this->assertEquals($value, 2, 'redis sCard 失败');
    }

    /**
     * Redis sRandMember 命令
     * @return \Generator
     */
    public function testRedisSRandMember()
    {
        $value = yield $this->redis_pool->getCoroutine()->sRandMember('testSet');
        $this->assertNotEmpty($value, 'redis sRandMember 失败');
    }

    /**
     * Redis sPop 命令
     * @return \Generator
     */
    public function testRedisSPop()
    {
        $value = yield $this->redis_pool->getCoroutine()->sPop('testSet');
        $this->assertNotEmpty($value, 'redis sPop 失败');
    }

    /**
     * Redis sMove 命令
     * @return \Generator
     */
    public function testRedisSMove()
    {
        yield $this->redis_pool->getCoroutine()->del(['testSet', 'testSet1']);
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        $value = yield $this->redis_pool->getCoroutine()->sMove('testSet', 'testSet1', 'index0');
        $this->assertTrue($value, 'redis sMove 失败');
        $value = yield $this->redis_pool->getCoroutine()->sPop('testSet1');
        $this->assertEquals($value, 'index0', 'redis sMove 失败');
    }

    /**
     * Redis sInter 命令
     * @return \Generator
     */
    public function testRedisSInter()
    {
        yield $this->redis_pool->getCoroutine()->del(['testSet', 'testSet1']);
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index1');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index1');
        $value = yield $this->redis_pool->getCoroutine()->sInter('testSet1', 'testSet');
        $this->assertCount(2, $value, 'redis sInter 失败');
    }

    /**
     * Redis sInterStore 命令
     * @return \Generator
     */
    public function testRedisSInterStore()
    {
        yield $this->redis_pool->getCoroutine()->del(['testSet', 'testSet1']);
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index1');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index1');
        yield $this->redis_pool->getCoroutine()->sInterStore('testSet2', 'testSet1', 'testSet');
        $value = yield $this->redis_pool->getCoroutine()->scard('testSet2');
        $this->assertEquals($value, 2, 'redis sInterStroe 失败');
    }

    /**
     * Redis sUnion 命令
     * @return \Generator
     */
    public function testRedisSUnion()
    {
        yield $this->redis_pool->getCoroutine()->del(['testSet', 'testSet1']);
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index1');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index2');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index3');
        $value = yield $this->redis_pool->getCoroutine()->sUnion('testSet1', 'testSet');
        $this->assertCount(4, $value, 'redis sUnion 失败');
    }

    /**
     * Redis sUnionStore 命令
     * @return \Generator
     */
    public function testRedisSUnionStore()
    {
        yield $this->redis_pool->getCoroutine()->del(['testSet', 'testSet1']);
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index1');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index2');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index3');
        yield $this->redis_pool->getCoroutine()->sUnionStore('testSet2', 'testSet1', 'testSet');
        $value = yield $this->redis_pool->getCoroutine()->scard('testSet2');
        $this->assertEquals($value, 4, 'redis sUnionStore 失败');
    }

    /**
     * Redis sDiff 命令
     * @return \Generator
     */
    public function testRedisSDiff()
    {
        yield $this->redis_pool->getCoroutine()->del(['testSet', 'testSet1']);
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index1');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index2');
        $value = yield $this->redis_pool->getCoroutine()->sUnion('testSet1', 'testSet');
        $this->assertCount(3, $value, 'redis sDiff 失败');
    }

    /**
     * Redis sDiffStore 命令
     * @return \Generator
     */
    public function testRedisSDiffStore()
    {
        yield $this->redis_pool->getCoroutine()->del(['testSet', 'testSet1']);
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index1');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet1', 'index2');
        yield $this->redis_pool->getCoroutine()->sUnionStore('testSet2', 'testSet1', 'testSet');
        $value = yield $this->redis_pool->getCoroutine()->scard('testSet2');
        $this->assertEquals($value, 3, 'redis sDiffStore 失败');
    }

    /**
     * Redis sMembers 命令
     * @return \Generator
     */
    public function testRedisSMembers()
    {
        yield $this->redis_pool->getCoroutine()->del('testSet');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index0');
        yield $this->redis_pool->getCoroutine()->sAdd('testSet', 'index1');
        $value = yield $this->redis_pool->getCoroutine()->sMembers('testSet');
        $this->assertCount(2, $value, 'redis sMembers 失败');
    }

    /**
     * Redis sort命令
     * @return \Generator
     */
    public function testRedisSort()
    {
        yield $this->redis_pool->getCoroutine()->del('testuid');
        yield $this->redis_pool->getCoroutine()->lpush('testuid', 1);
        yield $this->redis_pool->getCoroutine()->set('testuid_name_1', 'test1');
        yield $this->redis_pool->getCoroutine()->set('testuid_level_1', 10);
        yield $this->redis_pool->getCoroutine()->lpush('testuid', 2);
        yield $this->redis_pool->getCoroutine()->set('testuid_name_2', 'test2');
        yield $this->redis_pool->getCoroutine()->set('testuid_level_2', 20);
        $value = yield $this->redis_pool->getCoroutine()->sort('testuid', ['sort' => 'desc']);
        $this->assertCount(2, $value, 'redis Sort 失败');
        $value = yield $this->redis_pool->getCoroutine()->sort('testuid', ['by' => 'testuid_level_*', 'sort' => 'desc']);
        $this->assertCount(2, $value, 'redis Sort 失败');
        $value = yield $this->redis_pool->getCoroutine()->sort('testuid', ['by' => 'testuid_level_*', 'get' => ['testuid_name_*', '#', 'testuid_level_*'], 'sort' => 'desc']);
        $this->assertCount(6, $value, 'redis Sort 失败');
    }

    /**
     * Redis getSet 命令
     * @return \Generator
     */
    public function testRedisGetSet()
    {
        yield $this->redis_pool->getCoroutine()->set('test', 42);
        $value = yield $this->redis_pool->getCoroutine()->getSet('test', 'lol');
        $this->assertEquals($value, 42, 'redis getSet 失败');
        $value = yield $this->redis_pool->getCoroutine()->get('test');
        $this->assertEquals($value, 'lol', 'redis getSet 失败');
    }

    /**
     * Redis append 命令
     * @return \Generator
     */
    public function testRedisAppend()
    {
        yield $this->redis_pool->getCoroutine()->set('test', 'test');
        yield $this->redis_pool->getCoroutine()->append('test', 'lol');
        $value = yield $this->redis_pool->getCoroutine()->get('test');
        $this->assertEquals($value, 'testlol', 'redis append 失败');
    }

    /**
     * Redis strlen 命令
     * @return \Generator
     */
    public function testRedisStrlen()
    {
        yield $this->redis_pool->getCoroutine()->set('test', 'test');
        $value = yield $this->redis_pool->getCoroutine()->strlen('test');
        $this->assertEquals($value, '4', 'redis strlen 失败');
    }

    /**
     * Redis hset 命令
     * @return \Generator
     */
    public function testRedisHSet()
    {
        yield $this->redis_pool->getCoroutine()->hset('testHash', 'key0', 'value0');
        $value = yield $this->redis_pool->getCoroutine()->keys('testHash');
        $this->assertContains('testHash', $value, 'redis hset 失败');
    }

    /**
     * Redis hget 命令
     * @return \Generator
     */
    public function testRedisHGet()
    {
        $value = yield $this->redis_pool->getCoroutine()->hget('testHash', 'key0');
        $this->assertEquals($value, 'value0', 'redis hget 失败');
    }

    /**
     * Redis hlen 命令
     * @return \Generator
     */
    public function testRedisHLen()
    {
        $value = yield $this->redis_pool->getCoroutine()->hlen('testHash');
        $this->assertEquals($value, 1, 'redis hlen 失败');
    }

    /**
     * Redis hdel 命令
     * @return \Generator
     */
    public function testRedisHDel()
    {
        yield $this->redis_pool->getCoroutine()->hdel('testHash', 'key0');
        $value = yield $this->redis_pool->getCoroutine()->hlen('testHash');
        $this->assertEquals($value, 0, 'redis hdel 失败');
    }

    /**
     * Redis hkeys 命令
     * @return \Generator
     */
    public function testRedisHKeys()
    {
        yield $this->redis_pool->getCoroutine()->hset('testHash', 'key0', 'value0');
        $value = yield $this->redis_pool->getCoroutine()->hkeys('testHash');
        $this->assertContains('key0', $value, 'redis hkeys 失败');
    }

    /**
     * Redis hvals 命令
     * @return \Generator
     */
    public function testRedisHVals()
    {
        $value = yield $this->redis_pool->getCoroutine()->hvals('testHash');
        $this->assertContains('value0', $value, 'redis hvals 失败');
    }

    /**
     * Redis hgetall 命令
     * @return \Generator
     */
    public function testRedisHGetAll()
    {
        $value = yield $this->redis_pool->getCoroutine()->hGetAll('testHash');
        $this->assertEquals($value['key0'], 'value0', 'redis hgetall 失败');
    }

    /**
     * Redis HExists 命令
     * @return \Generator
     */
    public function testRedisHExists()
    {
        $value = yield $this->redis_pool->getCoroutine()->hExists('testHash', 'key0');
        $this->assertTrue($value, 'redis hExists 失败');
    }

    /**
     * Redis hIncrBy 命令
     * @return \Generator
     */
    public function testRedisHIncrBy()
    {
        yield $this->redis_pool->getCoroutine()->hset('testHash', 'key1', 1);
        yield $this->redis_pool->getCoroutine()->hIncrBy('testHash', 'key1', 10);
        $value = yield $this->redis_pool->getCoroutine()->hget('testHash', 'key1');
        $this->assertEquals($value, 11, 'redis hIncrBy 失败');
    }

    /**
     * Redis hMset 命令
     * @return \Generator
     */
    public function testRedisHMset()
    {
        yield $this->redis_pool->getCoroutine()->hMset('testHash', ['key1' => 1, 'key2' => 2]);
        $value = yield $this->redis_pool->getCoroutine()->hget('testHash', 'key1');
        $this->assertEquals($value, 1, 'redis hMset 失败');
        $value = yield $this->redis_pool->getCoroutine()->hget('testHash', 'key2');
        $this->assertEquals($value, 2, 'redis hMset 失败');
    }

    /**
     * Redis hMget 命令
     * @return \Generator
     */
    public function testRedisHMget()
    {
        $value = yield $this->redis_pool->getCoroutine()->hMget('testHash', ['key1', 'key2']);
        $this->assertEquals($value['key1'], 1, 'redis hMget 失败');
        $this->assertEquals($value['key2'], 2, 'redis hMget 失败');
    }

    /**
     * Redis zadd 命令
     * @return \Generator
     */
    public function testRedisZAdd()
    {
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol5');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');
        $value = yield $this->redis_pool->getCoroutine()->keys('testZset');
        $this->assertContains('testZset', $value, 'redis zadd 失败');
    }

    /**
     * Redis zrange 命令
     * @return \Generator
     */
    public function testRedisZRange()
    {
        $value = yield $this->redis_pool->getCoroutine()->zRange('testZset', 0, -1);
        $this->assertContains('vol0', $value, 'redis zrange 失败');
        $this->assertContains('vol5', $value, 'redis zrange 失败');
        $this->assertContains('vol2', $value, 'redis zrange 失败');
        $value = yield $this->redis_pool->getCoroutine()->zRange('testZset', 0, -1, true);
        $this->assertEquals($value['vol0'], 0, 'redis zrange 失败');
        $this->assertEquals($value['vol2'], 2, 'redis zrange 失败');
        $this->assertEquals($value['vol5'], 5, 'redis zrange 失败');
    }

    /**
     * Redis zRevRange 命令
     * @return \Generator
     */
    public function testRedisZRevRange()
    {
        $value = yield $this->redis_pool->getCoroutine()->zRevRange('testZset', 0, -1);
        $this->assertContains('vol0', $value, 'redis zRevRange 失败');
        $this->assertContains('vol5', $value, 'redis zRevRange 失败');
        $this->assertContains('vol2', $value, 'redis zRevRange 失败');
        $value = yield $this->redis_pool->getCoroutine()->zRevRange('testZset', 0, -1, true);
        $this->assertEquals($value['vol0'], 0, 'redis zRevRange 失败');
        $this->assertEquals($value['vol2'], 2, 'redis zRevRange 失败');
        $this->assertEquals($value['vol5'], 5, 'redis zRevRange 失败');
    }

    /**
     * Redis zCount 命令
     * @return \Generator
     */
    public function testRedisZCount()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol5');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');
        $value = yield $this->redis_pool->getCoroutine()->zCount('testZset', 0, 5);
        $this->assertEquals($value, 3, 'redis zCount 失败');
    }

    /**
     * Redis zCard 命令
     * @return \Generator
     */
    public function testRedisZCard()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol5');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');
        $value = yield $this->redis_pool->getCoroutine()->zCard('testZset');
        $this->assertEquals($value, 3, 'redis zCard 失败');
    }

    /**
     * Redis zRem 命令
     * @return \Generator
     */
    public function testRedisZRem()
    {
        $value = yield $this->redis_pool->getCoroutine()->zRem('testZset', 'vol0');
        $this->assertTrue($value, 'redis zRem 失败');
        $value = yield $this->redis_pool->getCoroutine()->zCard('testZset');
        $this->assertEquals($value, 2, 'redis zRem 失败');
    }

    /**
     * Redis zScore 命令
     * @return \Generator
     */
    public function testRedisZScore()
    {
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        $value = yield $this->redis_pool->getCoroutine()->zScore('testZset', 'vol0');
        $this->assertEquals($value, 0, 'redis zScore 失败');
    }

    /**
     * Redis zRank 命令
     * @return \Generator
     */
    public function testRedisZRank()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol5');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');
        $value = yield $this->redis_pool->getCoroutine()->zRank('testZset', 'vol2');
        $this->assertEquals($value, 1, 'redis zRank 失败');
    }

    /**
     * Redis zRevRank 命令
     * @return \Generator
     */
    public function testRedisZRevRank()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol5');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');
        $value = yield $this->redis_pool->getCoroutine()->zRevRank('testZset', 'vol2');
        $this->assertEquals($value, 1, 'redis zRevRank 失败');
    }

    /**
     * Redis zIncrBy 命令
     * @return \Generator
     */
    public function testRedisZIncrBy()
    {
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');
        yield $this->redis_pool->getCoroutine()->zIncrBy('testZset', 10, 'vol2');
        $value = yield $this->redis_pool->getCoroutine()->zScore('testZset', 'vol2');
        $this->assertEquals($value, 12, 'redis zIncrBy 失败');
    }

    /**
     * Redis zRangeByScore 命令
     * @return \Generator
     */
    public function testRedisZRangeByScore()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol5');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');
        $value = yield $this->redis_pool->getCoroutine()->zRangeByScore('testZset', 0, 5);
        $this->assertContains('vol0', $value, 'redis zRangeByScore 失败');
        $this->assertContains('vol5', $value, 'redis zRangeByScore 失败');
        $this->assertContains('vol2', $value, 'redis zRangeByScore 失败');
        $value = yield $this->redis_pool->getCoroutine()->zRangeByScore('testZset', 0, 5, ['withscores' => true, 'limit' => [0, 5]]);
        $this->assertEquals($value['vol0'], 0, 'redis zRangeByScore 失败');
        $this->assertEquals($value['vol2'], 2, 'redis zRangeByScore 失败');
        $this->assertEquals($value['vol5'], 5, 'redis zRangeByScore 失败');
    }

    /**
     * Redis zRevRangeByScore 命令
     * @return \Generator
     */
    public function testRedisZRevRangeByScore()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol5');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');
        $value = yield $this->redis_pool->getCoroutine()->zRangeByScore('testZset', 0, 5);
        $this->assertContains('vol0', $value, 'redis zRevRangeByScore 失败');
        $this->assertContains('vol5', $value, 'redis zRevRangeByScore 失败');
        $this->assertContains('vol2', $value, 'redis zRevRangeByScore 失败');
        $value = yield $this->redis_pool->getCoroutine()->zRangeByScore('testZset', 0, 5, ['withscores' => true, 'limit' => [0, 5]]);
        $this->assertEquals($value['vol0'], 0, 'redis zRevRangeByScore 失败');
        $this->assertEquals($value['vol2'], 2, 'redis zRevRangeByScore 失败');
        $this->assertEquals($value['vol5'], 5, 'redis zRevRangeByScore 失败');
    }

    /**
     * Redis zRemRangeByScore 命令
     * @return \Generator
     */
    public function testRedisZRemRangeByScore()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0', 5, 'vol5', 2, 'vol2');
        yield $this->redis_pool->getCoroutine()->zRemRangeByScore('testZset', 0, 5);
        $value = yield $this->redis_pool->getCoroutine()->zCard('testZset');
        $this->assertEquals($value, 0, 'redis zRemRangeByScore 失败');
    }

    /**
     * Redis ZUnion命令
     * @return \Generator
     */
    public function testRedisZUnion()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol5');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol2');

        yield $this->redis_pool->getCoroutine()->del('testZset1');
        yield $this->redis_pool->getCoroutine()->zadd('testZset1', 1, 'vol1');
        yield $this->redis_pool->getCoroutine()->zadd('testZset1', 3, 'vol3');
        yield $this->redis_pool->getCoroutine()->zadd('testZset1', 4, 'vol4');

        yield $this->redis_pool->getCoroutine()->zunion('testZset2', ['testZset', 'testZset1'], [1, 2], 'SUM');
        $value = yield $this->redis_pool->getCoroutine()->zRange('testZset2', 0, -1);
        $this->assertCount(6, $value, 'redis ZUnion 失败');
    }

    /**
     * Redis ZInter命令
     * @return \Generator
     */
    public function testRedisZInter()
    {
        yield $this->redis_pool->getCoroutine()->del('testZset');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 0, 'vol0');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 5, 'vol3');
        yield $this->redis_pool->getCoroutine()->zadd('testZset', 2, 'vol4');

        yield $this->redis_pool->getCoroutine()->del('testZset1');
        yield $this->redis_pool->getCoroutine()->zadd('testZset1', 1, 'vol1');
        yield $this->redis_pool->getCoroutine()->zadd('testZset1', 3, 'vol3');
        yield $this->redis_pool->getCoroutine()->zadd('testZset1', 4, 'vol4');

        yield $this->redis_pool->getCoroutine()->zInter('testZset2', ['testZset', 'testZset1'], [1, 2], 'SUM');
        $value = yield $this->redis_pool->getCoroutine()->zRange('testZset2', 0, -1);
        $this->assertCount(2, $value, 'redis zInter 失败');
    }
}