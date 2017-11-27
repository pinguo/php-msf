<?php
/**
 * Server状态运行状态控制台
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\MSF\Macro;

/**
 * Class Monitor
 * @package PG\MSF\Controllers
 */
class Monitor extends Controller
{
    /**
     * Server运行状态
     */
    public function actionIndex()
    {
        $data  = getInstance()->sysCache->get(Macro::SERVER_STATS);

        if ($data) {
            $concurrency = 0;
            foreach ($data['worker'] as $id => $worker) {
                if (!empty($worker['coroutine']['total'])) {
                    $concurrency += $worker['coroutine']['total'];
                }
            }
            $data['running']['concurrency'] = $concurrency;
            $data['sys_cache']              = getInstance()->sysCache->info();
            $this->outputJson($data);
        } else {
            $data                           = [];
            $data['sys_cache']              = getInstance()->sysCache->info();
            $this->outputJson($data);
        }
    }
}
