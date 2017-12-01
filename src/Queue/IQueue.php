<?php
/**
 * IQueue接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Queue;

interface IQueue
{
    /**
     * 入队
     * @param string $queue
     * @param string $data
     * @return mixed
     */
    public function set(string $data, string $queue = 'default');

    /**
     * 出队
     * @param string $queue
     * @return mixed
     */
    public function get(string $queue = 'default');
}
