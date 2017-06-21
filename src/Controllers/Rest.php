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
    /**
     * POST
     */
    public function httpCreate()
    {
        var_dump($this->verb);
        var_dump($this->getContext()->getInput()->getAllPostGet());

        $this->outputJson(11, 'shibaile', 403);
    }

    /**
     * GET
     */
    public function httpIndex()
    {
        var_dump($this->verb);
        $data = [
            [
                'f1' => $this->getContext()->getInput()->get('p1'),
                'f2' =>$this->getContext()->getInput()->get('p2'),
            ],
            [
                'f1' => $this->getContext()->getInput()->get('p1'),
                'f2' =>$this->getContext()->getInput()->get('p2'),
            ]
        ];
        $this->outputJson($data);
    }

    /**
     * GET
     */
    public function httpView()
    {
        var_dump($this->verb);
        $data = [
            'f1' => $this->getContext()->getInput()->get('p1'),
            'f2' =>$this->getContext()->getInput()->get('p2'),
        ];
        $this->outputJson($data);
    }

    /**
     * OPTIONS
     */
    public function httpOptions()
    {
        var_dump($this->verb);
        var_dump($this->getContext()->getInput()->getAllPostGet());
    }

    /**
     * PUT|PATCH
     */
    public function httpUpdate()
    {
        var_dump($this->verb);
        var_dump($this->getContext()->getInput()->getAllPostGet());
    }

    /**
     * DELETE
     */
    public function httpDelete()
    {
        var_dump($this->verb);
        var_dump($this->getContext()->getInput()->getAllPostGet());
    }
}
