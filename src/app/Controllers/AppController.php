<?php
namespace app\Controllers;

use app\Models\AppModel;
use Server\CoreBase\Controller;
use Server\DataBase\RedisAsynPool;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午3:51
 */
class AppController extends Controller
{
    /**
     * @var AppModel
     */
    public $AppModel;

    /**
     * @var RedisAsynPool
     */
    private $redis2;

    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->AppModel = $this->loader->model('AppModel', $this);
        //$this->redis2 = get_instance()->getAsynPool('redis2');
    }

    /**
     * http测试使用2个redis
     */
    public function http_test()
    {
        yield $this->redis_pool->getCoroutine()->set('redispool', 11);
        //yield $this->redis2->getCoroutine()->set('redispool', 22);
        $result = yield $this->redis_pool->getCoroutine()->get('redispool');
        print_r($result);
        //$result = yield $this->redis2->getCoroutine()->get('redispool');
        //print_r($result);
        $this->http_output->end($this->AppModel->test());
    }

    public function http_test_task()
    {
        $AppTask = $this->loader->task('AppTask');
        $AppTask->testTask();
        $AppTask->startTask(function ($serv, $task_id, $data) {
            $this->http_output->end($data);
        });
    }
}