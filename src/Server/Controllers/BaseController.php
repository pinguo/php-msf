<?php declare(strict_types = 1);
/**
 * @desc: 控制器基类
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/2/9
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

use PG\MSF\Server\CoreBase\Controller;
use PG\MSF\Server\Helpers\Log\PGLog;
use PG\MSF\Server\SwooleMarco;
use \PG\MSF\Server\CoreBase\CoroutineBase;

class BaseController extends Controller
{

    /**
     * @var PGLog
     */
    public $PGLog;

    public function initialization($controller_name, $method_name)
    {
        $this->PGLog = null;
        $this->PGLog = clone $this->logger;
        $this->PGLog->accessRecord['beginTime'] = microtime(true);
        $this->PGLog->accessRecord['uri'] = str_replace('\\', '/' ,'/' . $controller_name . '/'.$method_name);
        $this->getContext()['logId'] = $this->genLogId();
        $this->PGLog->logId = $this->getContext()['logId'];
        $this->PGLog->pushLogId();
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
            $logId = $this->http_input->getRequestHeader('LOG_ID') ?? '';
        } else {
            $logId = $this->client_data->logid ?? '';
        }

        if (!$logId) {
            $logId = strval(new \MongoId());
        }

        return $logId;
    }

    /**
     * 等待协程Task执行完成
     *
     * @param CoroutineBase $coroutine
     * @param int $traceLimit
     * @return mixed
     */
    function await($coroutine, $traceLimit = 1)
    {
        ob_start();
        debug_print_backtrace(0, $traceLimit);
        $trace = ob_get_contents();
        ob_end_clean();
        $this->getGeneratorContext()->setErrorMessage($trace);

        return $coroutine;
    }
}
