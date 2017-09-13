<?php
/**
 * Rest
 *
 * Restful 测试
 * 选择路由器：
 * $config['server']['route_tool'] = '\\PG\\MSF\\Route\\RestRoute';
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
 * 建议action：
 * [
 *     update -> 更新资源，如：/users/<id>
 *     delete -> 删除资源，如：/users/<id>
 *     view -> 查看资源单条数据，如：/users/<id>
 *     index -> 查看资源列表数据（可分页），如：/users
 *     options -> 查看资源所支持的HTTP动词，如：/users/<id> | /users
 *     create -> 新建资源，如：/users
 * ]
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\MSF\Rest\Controller;
use PG\MSF\Base\Output;

/**
 *
 * Class Rest
 * @package PG\MSF\Controllers
 */
class Rest extends Controller
{
    /**
     * POST
     * /rests
     * x-www-form-urlencoded
     * [
     *     'p1' => 1,
     *     'p2' => 'Hello',
     * ]
     */
    public function actionCreate()
    {
        $data = [
            'f1' => $this->getContext()->getInput()->post('p1'),
            'f2' => $this->getContext()->getInput()->post('p2'),
        ];

        $this->outputJson($data);
        //$this->outputJson(null, 'shibaile', 403);
    }

    /**
     * GET
     * /rests?p1=1&p2=Hello
     */
    public function actionIndex()
    {
        $data = [
            [
                'f1' => $this->getContext()->getInput()->get('p1'),
                'f2' => $this->getContext()->getInput()->get('p2'),
            ],
            [
                'f1' => $this->getContext()->getInput()->get('p1'),
                'f2' => $this->getContext()->getInput()->get('p2'),
            ]
        ];
        $this->outputJson($data);
        //$this->outputJson(null, 'canshucuowuyo', 401);
    }

    /**
     * GET
     * /rests/1?p1=1&p2=Hello
     */
    public function actionView()
    {
        $data = [
            'f1' => $this->getContext()->getInput()->get('p1'),
            'f2' => $this->getContext()->getInput()->get('p2'),
            'f3' => $this->getContext()->getInput()->get('id'),
        ];
        $this->outputJson($data);
    }

    /**
     * OPTIONS
     * /rests | /rests/1
     */
    public function actionOptions()
    {
        if ($this->getContext()->getInput()->get('id')) {
            $options = $this->resourceOptions;
        } else {
            $options = $this->collectionOptions;
        }
        $this->outputOptions($options);
    }

    /**
     * PUT|PATCH
     * /rests/1
     * x-www-form-urlencoded
     * [
     *     'p1' => 1,
     *     'p2' => 'Hello',
     * ]
     */
    public function actionUpdate()
    {
        $data = [
            'f1' => $this->getContext()->getInput()->post('p1'),
            'f2' => $this->getContext()->getInput()->post('p2'),
            'id' => $this->getContext()->getInput()->get('id')
        ];

        $this->outputJson($data);
    }

    /**
     * DELETE
     * /rests/1
     */
    public function actionDelete()
    {
        $this->outputJson(null, Output::$codes[204], 204);
    }
}
