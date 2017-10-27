<?php
/**
 * IQueue接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 * Date: 26/10/2017
 */

namespace PG\MSF\Queue;

interface IQueue
{
    /**
     * 入队
     * @param string $queue
     * @param $data
     * @return mixed
     */
    public function set(string $queue, $data);

    /**
     * 出队
     * @param string $queue
     * @return mixed
     */
    public function get(string $queue);
}
