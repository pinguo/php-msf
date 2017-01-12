<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-1-4
 * Time: 上午11:11
 */

namespace test;


use Server\Test\TestCase;

/**
 * 服务器框架mysql测试用例
 * @needTestTask
 * @package test
 */
class ServerMysqlTest extends TestCase
{

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function setUpBeforeClass()
    {
        $this->mysql_pool->dbQueryBuilder->coroutineSend(null, "
            CREATE TABLE IF NOT EXISTS `MysqlTest` (
              `peopleid` smallint(6) NOT NULL AUTO_INCREMENT,
              `firstname` char(50) NOT NULL,
              `lastname` char(50) NOT NULL,
              `age` smallint(6) NOT NULL,
              `townid` smallint(6) NOT NULL,
              PRIMARY KEY (`peopleid`),
              UNIQUE KEY `unique_fname_lname`(`firstname`,`lastname`),
              KEY `fname_lname_age` (`firstname`,`lastname`,`age`)
            ) ;
        ");
    }

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function tearDownAfterClass()
    {
        $this->mysql_pool->dbQueryBuilder->coroutineSend(null, "
            DROP TABLE  `MysqlTest`;
        ");
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
     * mysql Insert 命令
     * @return \Generator
     */
    public function testMysqlInsert()
    {
        $value = yield $this->mysql_pool->dbQueryBuilder->insert('MysqlTest')
            ->option('HIGH_PRIORITY')
            ->set('firstname', 'White')
            ->set('lastname', 'Cat')
            ->set('age', '25')
            ->set('townid', '10000')->coroutineSend();
        $this->assertEquals($value['result'], 1, 'Insert 失败');
        $this->assertEquals($value['affected_rows'], 1, 'Insert 失败');
        $this->assertEquals($value['insert_id'], 1, 'Insert 失败');
    }

    /**
     * mysql Replace 命令
     * @return \Generator
     */
    public function testMysqlReplace()
    {
        $value = yield $this->mysql_pool->dbQueryBuilder->replace('MysqlTest')
            ->set('firstname', 'White')
            ->set('lastname', 'Cat')
            ->set('age', '26')
            ->set('townid', '10000')->coroutineSend();
        $this->assertEquals($value['result'], 1, 'Replace 失败');
        $this->assertEquals($value['affected_rows'], 2, 'Replace 失败');
        $this->assertEquals($value['insert_id'], 2, 'Replace 失败');
    }

    /**
     * mysql Update 命令
     * @return \Generator
     */
    public function testMysqlUpdate()
    {
        $value = yield $this->mysql_pool->dbQueryBuilder->update('MysqlTest')
            ->set('age', '20')
            ->where('townid', 10000)->coroutineSend();
        $this->assertEquals($value['result'], 1, 'Update 失败');
        $this->assertEquals($value['affected_rows'], 1, 'Update 失败');
        $this->assertEquals($value['insert_id'], 0, 'Update 失败');
    }

    /**
     * mysql Select 命令
     * @return \Generator
     */
    public function testMysqlSelect()
    {
        $value = yield $this->mysql_pool->dbQueryBuilder->Select('*')
            ->from('MysqlTest')
            ->where('townid', 10000)->coroutineSend();
        $this->assertEquals($value['result'][0]['age'], 20, 'Update 失败');
        $this->assertEquals($value['affected_rows'], 0, 'Select 失败');
        $this->assertEquals($value['insert_id'], 0, 'Select 失败');
    }

    /**
     * mysql Delete 命令
     * @return \Generator
     */
    public function testMysqlDelete()
    {
        $value = yield $this->mysql_pool->dbQueryBuilder->delete()
            ->from('MysqlTest')
            ->where('townid', 10000)->coroutineSend();
        $this->assertEquals($value['result'], 1, 'Delete 失败');
        $this->assertEquals($value['affected_rows'], 1, 'Delete 失败');
        $this->assertEquals($value['insert_id'], 0, 'Delete 失败');
    }
}