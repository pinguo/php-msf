<?php

/**
 * Rest Controller
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Rest;

use PG\MSF\Base\Output;

/**
 * Class Controller
 * @package PG\MSF\Controllers
 */
class Controller extends \PG\MSF\Controllers\Controller
{
    /**
     * @var array
     */
    public static $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Action Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    ];
    /**
     * @var array
     */
    public $patterns = [
        'PUT,PATCH {id}' => 'update',
        'DELETE {id}' => 'delete',
        'GET,HEAD {id}' => 'view',
        'POST' => 'create',
        'GET,HEAD' => 'index',
        '{id}' => 'options',
        '' => 'options',
    ];

    /**
     * index [GET, HEAD] 查看资源列表数据（可分页），如：/users
     */
    public function httpIndex()
    {

    }

    /**
     * view [GET, HEAD] 查看资源单条数据，如：/users/<id>
     */
    public function httpView()
    {

    }

    /**
     * create [POST] 新建资源，如：/users
     */
    public function httpCreate()
    {

    }

    /**
     * update [PUT, PATCH] 更新资源，如：/users/<id>
     */
    public function httpUpdate()
    {

    }

    /**
     * delete [DELETE] 删除资源，如：/users/<id>
     */
    public function httpDelete()
    {

    }

    /**
     * options [OPTIONS] 查看资源所支持的HTTP动词，如：/users/<id> | /users
     */
    public function httpOptions()
    {

    }

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
        $output->setStatusHeader($status);
        $result = json_encode($data);
        if (!empty($output->response)) {
            $output->setContentType('application/json; charset=UTF-8');
            $output->end($result);
        }
    }
}
