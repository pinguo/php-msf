<?php
/**
 * Server状态
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

use PG\MSF\Server\Marco;

class Server extends BaseController
{
    public function HttpInfo()
    {
        $cache = new \Yac();
        $data  = $cache->get(Marco::SERVER_STATS);
        if ($data) {
            $this->outputJson($data, 'Server Information');
        } else {
            $this->outputJson([],    'Server Information Not OK');
        }
    }

    /**
     * Http 服务状态探测
     */
    public function HttpStatus()
    {
        $this->outputJson('ok');
    }

    /**
     * Tcp 服务状态探测
     */
    public function TcpStatus()
    {
        $this->outputJson('ok');
    }
}