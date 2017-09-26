<?php
/**
 * 协程接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

/**
 * Interface IBase
 * @package PG\MSF\Coroutine
 */
interface IBase
{
    /**
     * 协程是否超时
     *
     * @return mixed
     */
    public function isTimeout();

    /**
     * 发送异步请求
     *
     * @param $callback
     */
    public function send($callback);

    /**
     * 获取协程执行结果
     *
     * @return mixed
     */
    public function getResult();
}
