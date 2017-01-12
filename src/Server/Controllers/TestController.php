<?php
namespace Server\Controllers;

use Server\CoreBase\Controller;
use Server\Models\TestModel;
use Server\Tasks\TestTask;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午3:51
 */
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
        $this->send($this->client_data->data);
    }

    /**
     * mysql 事务协程测试
     */
    public function http_mysql_begin_coroutine_test()
    {
        $id = yield $this->mysql_pool->coroutineBegin($this);
        $update_result = yield $this->mysql_pool->dbQueryBuilder->update('user_info')->set('sex', '0')->where('uid', 36)->coroutineSend($id);
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from('user_info')->where('uid', 36)->coroutineSend($id);
        if ($result['result'][0]['channel'] == 888) {
            $this->http_output->end('commit');
            yield $this->mysql_pool->coroutineCommit($id);
        } else {
            $this->http_output->end('rollback');
            yield $this->mysql_pool->coroutineRollback($id);
        }
    }
    /**
     * 绑定uid
     */
    public function bind_uid()
    {
        $this->bindUid($this->fd, $this->client_data->data);
        $this->destroy();
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test()
    {
        $data = $this->client_data->data;
        $this->sendToUid(mt_rand(1, 100), $data);
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test2()
    {
        $data = $this->client_data->data;
        $this->send($data);
    }

    /**
     * mysql效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function mysql_efficiency()
    {
        yield $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->where('uid', 10004)->coroutineSend();
        $this->send($this->client_data->data);
    }

    /**
     * 获取mysql语句
     */
    public function http_mysqlStatement()
    {
        $value = $this->mysql_pool->dbQueryBuilder->insertInto('account')->intoColumns(['uid', 'static'])->intoValues([[36, 0], [37, 0]])->getStatement(true);
        $this->http_output->end($value);
    }
    /**
     * http测试
     */
    public function http_test()
    {
        $this->http_output->end('helloworld', false);
    }

    /**
     * http redis 测试
     */
    public function http_redis()
    {
        $value = $this->redis_pool->getCoroutine()->get('test');
        yield $value;
        $value1 = $this->redis_pool->getCoroutine()->get('test1');
        yield $value1;
        $value2 = $this->redis_pool->getCoroutine()->get('test2');
        yield $value2;
        $value3 = $this->redis_pool->getCoroutine()->get('test3');
        yield $value3;
        $this->http_output->end(1, false);
    }

    /**
     * http 同步redis 测试
     */
    public function http_aredis()
    {
        $value = get_instance()->getRedis()->get('test');
        $value1 = get_instance()->getRedis()->get('test1');
        $value2 = get_instance()->getRedis()->get('test2');
        $value3 = get_instance()->getRedis()->get('test3');
        $this->http_output->end(1, false);
    }
    /**
     * html测试
     */
    public function http_html_test()
    {
        $template = $this->loader->view('server::error_404');
        $this->http_output->end($template->render(['controller' => 'TestController\html_test', 'message' => '页面不存在！']));
    }

    /**
     * html测试
     */
    public function http_html_file_test()
    {
        $this->http_output->endFile(SERVER_DIR, 'Views/test.html');
    }


    /**
     * 协程的httpclient测试
     */
    public function http_test_httpClient()
    {
        $httpClient = yield $this->client->coroutineGetHttpClient('http://localhost:8081');
        $result = yield $httpClient->coroutineGet("/TestController/test_request", ['id' => 123]);
        $this->http_output->end($result);
    }

    /**
     * @return boolean
     */
    public function isIsDestroy()
    {
        return $this->is_destroy;
    }

}