<?php
/**
 * TestTcpCoroutine
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Test;

use PG\MSF\Server\Coroutine\CoroutineBase;
use PG\MSF\Server\Coroutine\CoroutineNull;

class TestTcpCoroutine extends CoroutineBase
{
    /**
     * @var \PG\MSF\Server\CoreBase\Controller|void
     */
    private $controller;

    public function __construct($data, $uid = 0)
    {
        parent::__construct();
        $this->request = '#TcpRequest:' . json_encode($data);
        $data = getInstance()->pack->pack($data);
        $data = getInstance()->encode($data);
        try {
            $this->controller = getInstance()->onSwooleReceive(getInstance()->server, $uid, 0, $data);
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
        if ($this->controller->isDestroy) {
            $result = $this->controller->getTestUnitResult();
        }
        return $result;
    }
}