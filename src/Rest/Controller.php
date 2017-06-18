<?php

/**
 * Rest Controller
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Rest;

use PG\Exception\Errno;
use PG\MSF\Base\Output;

/**
 * Class Controller
 * @package PG\MSF\Controllers
 */
class Controller extends \PG\MSF\Controllers\Controller
{
    /**
     * @var array the HTTP verbs that are supported by the collection URL
     */
    public $collectionOptions = ['GET', 'POST', 'HEAD', 'OPTIONS'];
    /**
     * @var array the HTTP verbs that are supported by the resource URL
     */
    public $resourceOptions = ['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /**
     * output Json
     * @param null $data
     * @param string $message
     * @param int $status
     * @param null $callback
     */
    public function outputJson($data = null, $message = '', $status = 200, $callback = null)
    {
        /* @var $output Output */
        $output = $this->getContext()->getOutput();
        // set status in header
        if (!in_array($status, array_keys(Output::$codes))) {
            throw new \Exception('Http code invalid', Errno::FATAL);
        }
        $output->setStatusHeader($status);
        // 错误信息返回格式可参考：[https://developer.github.com/v3/]
        if ($status != 200 && $message !== '') {
            $data = [
                'message' => $message
            ];
        }
        $result = json_encode($data);
        if (!empty($output->response)) {
            $output->setContentType('application/json; charset=UTF-8');
            $output->end($result);
        }
    }
}
