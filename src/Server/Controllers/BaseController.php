<?php declare(strict_types=1);
/**
 * @desc: 控制器基类
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/2/9
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

use PG\MSF\Server\CoreBase\Controller;
use PG\MSF\Server\SwooleMarco;

class BaseController extends Controller
{
    /**
     * 访问请求日志变量，此变量不用unset，因为每次请求initialization都会重新赋值
     * @var array
     */
    public $accessLog;
    public $logId;

    public function initialization($controller_name, $method_name)
    {
        $this->accessLog['path'] = '/' . $controller_name . '/' . $method_name;
        $this->logId = $this->genLogId();
        $this->logger->pushLogId($this->logId);
    }

    public function destroy()
    {
        parent::destroy();
    }

    /**
     * gen a logId
     * @return string
     */
    public function genLogId()
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            $logId = $this->http_input->getRequestHeader('logid') ?? '';
        } else {
            $logId =  $this->client_data->logid ?? '';
        }

        if (!$logId) {
            $logId = strval(new \MongoId());
        }

        return $logId;
    }
}
