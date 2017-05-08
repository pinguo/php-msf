<?php
/**
 * Server状态
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\MSF\Marco;

class Server extends BaseController
{
    public function HttpInfo()
    {
        $data  = getInstance()->sysCache->get(Marco::SERVER_STATS);

        if ($data) {
            $concurrency = 0;
            foreach ($data['worker'] as $id => $worker) {
                if (!empty($worker['coroutine']['total'])) {
                    $concurrency += $worker['coroutine']['total'];
                }
            }
            $data['running']['concurrency'] = $concurrency;
            $data['sys_cache']              = getInstance()->sysCache->info();
            $this->outputJson($data, 'Server Information');
        } else {
            $data                           = [];
            $data['sys_cache']              = getInstance()->sysCache->info();
            $this->outputJson($data,    'Server Information Not OK');
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