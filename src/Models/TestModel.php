<?php
/**
 * TestModel
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Models;

use PG\MSF\Models\Model;
use PG\MSF\Base\Exception;

class TestModel extends Model
{
    public function timerTest()
    {
        print_r("model timer\n");
    }

    public function contextTest()
    {
        print_r($this->getContext());
        $testTask = $this->getLoader()->task('TestTask', $this);
        $testTask->contextTest();
        $testTask->startTask(null);
    }

    public function testCoroutine()
    {
        $redisCoroutine = $this->redisPool->coroutineSend('get', 'test');
        $result = yield $redisCoroutine;
        return $result;
    }

    public function testCoroutineII($callback)
    {
        $this->redisPool->get('test', function ($uid) use ($callback) {
            $this->mysqlPool->dbQueryBuilder->select('*')->from('account')->where('uid', $uid);
            $this->mysqlPool->query(function ($result) use ($callback) {
                $callback($result);
            });
        });
    }

    public function testException()
    {
        throw new Exception('test');
    }

    public function testExceptionII()
    {
        $result = yield $this->redisPool->coroutineSend('get', 'test');
        $result = yield $this->mysqlPool->dbQueryBuilder->select('*')->where('uid', 10303)->coroutineSend();
    }

    public function testTask()
    {
        $testTask = $this->getLoader()->task('TestTask', $this);
        $testTask->test();
        $testTask->startTask(null);
    }

    public function testPdo()
    {
        $result = yield $this->mysqlPool->dbQueryBuilder->select('*')->from('account')->where('uid',
            36)->coroutineSend();
        $result = yield $this->mysqlPool->dbQueryBuilder->update('account')->where('uid',
            36)->set(['status' => 1])->coroutineSend();
        $result = yield $this->mysqlPool->dbQueryBuilder->replace('account')->where('uid',
            91)->set(['status' => 1])->coroutineSend();
        print_r($result);
    }
}
