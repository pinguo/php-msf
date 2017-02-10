<?php
namespace app\Controllers;

use app\Models\AppModel;
use Server\Controllers\BaseController;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午3:51
 */
class App extends BaseController
{
    /**
     * @var AppModel
     */
    public $AppModel;

    public function httpTest()
    {
        $t1 = microtime(true);
        $this->AppModel = $this->loader->model('AppModel', $this);
        $data = $this->AppModel->test();
        $t2 = microtime(true) - $t1;
        $data[] = $t2;
        $this->http_output->end(json_encode($data));
    }

    public function httpTestCurlMulti()
    {
        $t1 = microtime(true);
        $this->AppModel = $this->loader->model('AppModel', $this);
        $data = $this->AppModel->testCurlMulti();
        $t2 = microtime(true) - $t1;
        $data[] = $t2;
        $this->http_output->end(json_encode($data));
    }

    public function httpTestCoroutine()
    {
        $t1 = microtime(true);
        $this->AppModel = $this->loader->model('AppModel', $this);
        $data = yield $this->AppModel->testCoroutine();
        $t2 = microtime(true) - $t1;
        $data[] = $t2;
        $this->http_output->end(json_encode($data));
    }

    public function httpNoneNested()
    {
        $result = [];
        $httpClient1 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient2 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');

        $c1 = yield $httpClient1;
        $c2 = yield $httpClient2;

        $r1 = $c1->coroutineGet('/');
        $r2 = $c2->coroutineGet('/');

        $result[] = strlen(yield $r1);
        $result[] = strlen(yield $r2);
        
        $this->http_output->end(json_encode($result));
    }

    public function httpNested()
    {
        $t1 = microtime(true);
        $this->AppModel = $this->loader->model('AppModel', $this);
        $result = yield $this->AppModel->nested();
        $result[] = microtime(true) - $t1;

        $this->http_output->end(json_encode($result));
    }

    public function httpNested2()
    {
        $t1 = microtime(true);
        $this->AppModel = $this->loader->model('AppModel', $this);
        $result   = yield $this->AppModel->nested2();
        $result[] = microtime(true) - $t1;

        $this->http_output->end(json_encode($result));
    }

    public function httpTestLog()
    {
        $this->logger->error('this is an error log');
        $this->logger->notice('this is a notice log');
        $this->http_output->end('ok');
    }
}
