<?php
namespace app\Controllers;

use app\Models\AppModel;
use Server\CoreBase\Controller;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午3:51
 */
class App extends Controller
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

    public function httpTestCoroutine()
    {
        $t1 = microtime(true);
        $this->AppModel = $this->loader->model('AppModel', $this);
        $data = yield $this->AppModel->testCoroutine();
        $t2 = microtime(true) - $t1;
        $data[] = $t2;
        $this->http_output->end(json_encode($data));
    }

    public function httpTestLog()
    {
        $this->logger->error('this is an error log');
        $this->logger->notice('this is a notice log');
        $this->http_output->end('ok');
    }
}
