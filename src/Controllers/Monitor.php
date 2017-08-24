<?php
/**
 * Server状态运行状态控制台
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\MSF\Marco;
use PG\MSF\Client\Http\Client;

class Monitor extends Controller
{
    /**
     * Server运行状态
     */
    public function actionIndex()
    {
        $data  = yield $this->statistics();

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
            $this->outputJson($data, 'Server Information Not OK');
        }
    }

    /**
     * 汇总各个Worker的运行状态信息
     */
    private function statistics()
    {
        $data = [
            'worker' => [
                // worker进程ID
                // 'pid' => 0,
                // 协程统计信息
                // 'coroutine' => [
                // 当前正在处理的请求数
                // 'total' => 0,
                //],
                // 内存使用
                // 'memory' => [
                // 峰值
                // 'peak'  => '',
                // 当前使用
                // 'usage' => '',
                //],
                // 请求信息
                //'request' => [
                // 当前Worker进程收到的请求次数
                //'worker_request_count' => 0,
                //],
            ],
            'tcp' => [
                // 服务器启动的时间
                'start_time' => '',
                // 当前连接的数量
                'connection_num' => 0,
                // 接受了多少个连接
                'accept_count' => 0,
                // 关闭的连接数量
                'close_count' => 0,
                // 当前正在排队的任务数
                'tasking_num' => 0,
                // Server收到的请求次数
                'request_count' => 0,
                // 消息队列中的Task数量
                'task_queue_num' => 0,
                // 消息队列的内存占用字节数
                'task_queue_bytes' => 0,
            ],
        ];

        $workerIds = range(0, $this->getServerInstance()->setting['worker_num'] - 1);
        $request   = [];
        foreach ($workerIds as $workerId) {
            $request[$workerId] = "http://127.0.0.1:" . ($this->getConfig()['http_server']['port'] + $workerId + 1);
        }

        $result = yield $this->getObject(Client::class)->goConcurrent($request);

        foreach ($result as $workerId => $content) {
            $data['worker'][$workerId] = json_decode($content['body'], true);
        }
        $summary       = getInstance()->sysCache->get(Marco::SERVER_STATS) ?? [];
        $stat          = array_merge($data, $summary);

        return $stat;
    }
}
