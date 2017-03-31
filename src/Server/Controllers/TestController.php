<?php
/**
 * TestController
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

use PG\MSF\Server\CoreBase\Controller;
use PG\MSF\Server\CoreBase\SelectCoroutine;
use PG\MSF\Server\Models\TestModel;
use PG\MSF\Server\Tasks\TestTask;

class TestController extends Controller
{
    /**
     * @var TestTask
     */
    public $testTask;

    /**
     * @var TestModel
     */
    public $testModel;

    /**
     * tcp的测试
     */
    public function testTcp()
    {
        $this->send($this->clientData->data);
    }

    public function http_testContext()
    {
        $this->getContext()['test'] = 1;
        print_r($this->getContext());
        $this->testModel = $this->loader->model('TestModel', $this);
        $this->testModel->contextTest();
    }

    /**
     * mysql 事务协程测试
     */
    public function http_mysql_begin_coroutine_test()
    {
        $id = yield $this->mysqlPool->coroutineBegin($this);
        $updateResult = yield $this->mysqlPool->dbQueryBuilder->update('user_info')->set('sex', '0')->where('uid',
            36)->coroutineSend($id);
        $result = yield $this->mysqlPool->dbQueryBuilder->select('*')->from('user_info')->where('uid',
            36)->coroutineSend($id);
        if ($result['result'][0]['channel'] == 888) {
            $this->output->end('commit');
            yield $this->mysqlPool->coroutineCommit($id);
        } else {
            $this->output->end('rollback');
            yield $this->mysqlPool->coroutineRollback($id);
        }
    }

    /**
     * 绑定uid
     */
    public function bind_uid()
    {
        $this->bindUid($this->fd, $this->clientData->data);
        $this->destroy();
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test()
    {
        $data = $this->clientData->data;
        $this->sendToUid(mt_rand(1, 100), $data);
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test2()
    {
        $data = $this->clientData->data;
        $this->send($data);
    }

    /**
     * mysql效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function mysql_efficiency()
    {
        yield $this->mysqlPool->dbQueryBuilder->select('*')->from('account')->where('uid', 10004)->coroutineSend();
        $this->send($this->clientData->data);
    }

    /**
     * 获取mysql语句
     */
    public function http_mysqlStatement()
    {
        $value = $this->mysqlPool->dbQueryBuilder->insertInto('account')->intoColumns([
            'uid',
            'static'
        ])->intoValues([[36, 0], [37, 0]])->getStatement(true);
        $this->output->end($value);
    }

    /**
     * http测试
     */
    public function http_test()
    {
        $this->output->end('helloworld', false);
    }

    /**
     * http redis 测试
     */
    public function http_redis()
    {
        $value = $this->redisPool->getCoroutine()->get('test');
        yield $value;
        $value1 = $this->redisPool->getCoroutine()->get('test1');
        yield $value1;
        $value2 = $this->redisPool->getCoroutine()->get('test2');
        yield $value2;
        $value3 = $this->redisPool->getCoroutine()->get('test3');
        yield $value3;
        $this->output->end(1, false);
    }

    /**
     * http 同步redis 测试
     */
    public function http_aredis()
    {
        $value = getInstance()->getRedis()->get('test');
        $value1 = getInstance()->getRedis()->get('test1');
        $value2 = getInstance()->getRedis()->get('test2');
        $value3 = getInstance()->getRedis()->get('test3');
        $this->output->end(1, false);
    }

    /**
     * html测试
     */
    public function http_html_test()
    {
        $template = $this->loader->view('server::error_404');
        $this->output->end($template->render(['controller' => 'TestController\html_test', 'message' => '页面不存在！']));
    }

    /**
     * html测试
     */
    public function http_html_file_test()
    {
        $this->output->endFile(ROOT_PATH, 'Views/test.html');
    }


    /**
     * 协程的httpclient测试
     */
    public function http_test_httpClient()
    {
        $httpClient = yield $this->client->coroutineGetHttpClient('http://localhost:8081');
        $result = yield $httpClient->coroutineGet("/TestController/test_request", ['id' => 123]);
        $this->output->end($result);
    }

    /**
     * select方法测试
     * @return \Generator
     */
    public function http_test_select()
    {
        yield $this->redisPool->getCoroutine()->set('test', 1);
        $c1 = $this->redisPool->getCoroutine()->get('test');
        $c2 = $this->redisPool->getCoroutine()->get('test1');
        $result = yield SelectCoroutine::Select(function ($result) {
            if ($result != null) {
                return true;
            }
            return false;
        }, $c2, $c1);
        $this->output->end($result);
    }

    public function http_startInterruptedTask()
    {
        $testTask = $this->loader->task('TestTask', $this);
        $taskId = $testTask->testInterrupted();
        $testTask->startTask(null);
        $this->output->end("task_id = $taskId");
    }

    public function http_interruptedTask()
    {
        $taskId = $this->input->getPost('task_id');
        getInstance()->interruptedTask($taskId);
        $this->output->end("ok");
    }

    public function http_getAllTask()
    {
        $messages = getInstance()->getServerAllTaskMessage();
        $this->output->end(json_encode($messages));
    }

    /**
     * @return boolean
     */
    public function isIsDestroy()
    {
        return $this->isDestroy;
    }

}