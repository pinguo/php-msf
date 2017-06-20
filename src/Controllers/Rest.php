<?php
/**
 * RestTestController
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\MSF\Rest\Controller;

/**
 * Restful 测试
 * 选择路由器：
 * $config['server']['route_tool'] = '\\PG\\MSF\\Rest\\Route';
 * 路由相关配置 eg：
 * $config['rest']['route']['rules'] = [
 *     'POST rests' => 'rest/create',
 *     'GET rests/<id:\d+>' => 'rest/view',
 *     'GET rests/<action:\w+>' => 'rest/<action>',
 *     'GET rests' => 'rest/index',
 *     'PUT,PATCH rests/<id:\d+>' => 'rest/update',
 *     'DELETE rests/<id:\d+>' => 'rest/delete',
 *     'OPTIONS rests' => 'rest/options',
 *     'OPTIONS rests/<id:\d+>' => 'rest/options',
 * ];
 *
 * Class RestController
 * @package PG\MSF\Controllers
 */
class Rest extends Controller
{
    public function httpCreate()
    {
        var_dump($this->getContext()->getInput()->getAllPostGet());
        $this->outputJson(11, 'shibaile', 403);
    }

    public function httpIndex()
    {
        var_dump($this->getContext()->getInput()->getAllPostGet());
    }

    public function httpView()
    {
        var_dump($this->getContext()->getInput()->getAllPostGet());
    }

    public function httpDelete()
    {
        var_dump($this->getContext()->getInput()->getAllPostGet());
    }

    public function httpUpdate()
    {
        var_dump($this->getContext()->getInput()->getAllPostGet());
    }

    public function httpOptions()
    {
        var_dump($this->getContext()->getInput()->getAllPostGet());
    }
}
