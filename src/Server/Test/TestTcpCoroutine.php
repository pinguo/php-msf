<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-12-30
 * Time: 下午4:37
 */

namespace Server\Test;


use Server\CoreBase\CoroutineBase;
use Server\CoreBase\CoroutineNull;

class TestTcpCoroutine extends CoroutineBase
{
    /**
     * @var \Server\CoreBase\Controller|void
     */
    private $controller;

    public function __construct($data, $uid = 0)
    {
        parent::__construct();
        $this->request = '#TcpRequest:' . json_encode($data);
        $data = get_instance()->pack->pack($data);
        $data = get_instance()->encode($data);
        try {
            $this->controller = get_instance()->onSwooleReceive(get_instance()->server, $uid, 0, $data);
        } catch (\Exception $e) {
            $this->controller = CoroutineNull::getInstance();
        }
    }

    public function send($callback)
    {

    }

    public function getResult()
    {
        $result = parent::getResult();
        if ($this->controller == CoroutineNull::getInstance()) {
            return null;
        }
        if ($this->controller->is_destroy) {
            $result = $this->controller->getTestUnitResult();
        }
        return $result;
    }
}