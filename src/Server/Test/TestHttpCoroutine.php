<?php
/**
 * TestHttpCoroutine
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Test;

use PG\MSF\Server\CoreBase\CoroutineBase;

class TestHttpCoroutine extends CoroutineBase
{
    /**
     * @var TestRequest
     */
    public $testRequest;
    public $testResponse;

    public function __construct(TestRequest $testRequest)
    {
        parent::__construct();
        $this->testRequest = $testRequest;
        $this->request = '#TestRequest:' . $testRequest->server['path_info'];
        $this->testResponse = new TestResponse();
        getInstance()->onSwooleRequest($this->testRequest, $this->testResponse);
    }

    public function send($callback)
    {

    }

    public function getResult()
    {
        parent::getResult();
        $result = $this->testResponse->getResult();
        return $result;
    }
}