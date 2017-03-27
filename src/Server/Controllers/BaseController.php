<?php declare(strict_types=1);
/**
 * @desc: 控制器基类
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/2/9
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

use PG\MSF\Server\{
    CoreBase\Controller, Helpers\Log\PGLog, SwooleMarco, Helpers\Context
};

class BaseController extends Controller
{
    /**
     * @var PGLog
     */
    public $PGLog;

    /**
     * @var float 请求开始处理的时间
     */
    public $requestStartTime = 0.0;

    public function initialization($controller_name, $method_name)
    {
        $this->requestStartTime = microtime(true);
        $this->PGLog = null;
        $this->PGLog = clone $this->logger;
        $this->PGLog->accessRecord['beginTime'] = microtime(true);
        $this->PGLog->accessRecord['uri'] = str_replace('\\', '/', '/' . $controller_name . '/' . $method_name);
        $this->getContext()['logId'] = $this->genLogId();
        $this->PGLog->logId = $this->getContext()['logId'];
        defined('SYSTEM_NAME') && $this->PGLog->channel = SYSTEM_NAME;
        $this->PGLog->init();

        $context                           = new Context();
        $context->PGLog                    = $this->PGLog;
        $this->client->context             = $context;
        $this->tcpClient->context          = $context;
        $this->client->context->controller = &$this;
    }

    public function destroy()
    {
        $this->PGLog->appendNoticeLog();
        parent::destroy();
    }

    /**
     * gen a logId
     * @return string
     */
    public function genLogId()
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            $logId = $this->http_input->getRequestHeader('log_id') ?? '';
        } else {
            $logId = $this->client_data->logId ?? '';
        }

        if (!$logId) {
            $logId = strval(new \MongoId());
        }

        return $logId;
    }

    /**
     * 响应json格式数据
     *
     * @param null $data
     * @param string $message
     * @param int $status
     * @param null $callback
     * @return array
     */
    public function outputJson($data = null, $message = '', $status = 200, $callback = null)
    {
        $callback = $this->getCallback($callback);
        $result = [
            'data' => $data,
            'status' => $status,
            'message' => $message,
            'serverTime' => microtime(true),
        ];

        if (!is_null($callback)) {
            $output = $callback . '(' . json_encode($result) . ');';
        } else {
            $output = json_encode($result);
        }

        switch ($this->request_type) {
            case SwooleMarco::HTTP_REQUEST:
                if (!empty($this->http_output->response)) {
                    $this->http_output->setContentType('application/json; charset=UTF-8');
                    $this->http_output->end($output);
                }
                break;
            case SwooleMarco::TCP_REQUEST:
                $this->send($output);
                break;
        }

    }

    /**
     * 获取jsonp的callback名称
     *
     * @param $callback
     * @return string
     */
    public function getCallback($callback)
    {
        if (is_null($callback) && (!empty($this->http_input->postGet('callback'))
                || !empty($this->http_input->postGet('cb')) || !empty($this->http_input->postGet('jsonpCallback')))
        ) {
            $callback = !empty($this->http_input->postGet('callback'))
                ? $this->http_input->postGet('callback')
                : !empty($this->http_input->postGet('cb'))
                    ? $this->http_input->postGet('cb')
                    : $this->http_input->postGet('jsonpCallback');
        }

        return $callback;
    }

    /**
     * 异常的回调
     *
     * @param \Throwable $e
     * @throws \PG\MSF\Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function onExceptionHandle(\Throwable $e)
    {
        $message = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $message .= ' trace: ' . $e->getTraceAsString();

        $this->PGLog->error($message);

        $this->outputJson([], 'error', 500);
    }
}
