<?php
/**
 * proxy handle interface
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

/**
 * Interface IProxy
 * @package PG\MSF\Proxy
 */
interface IProxy
{
    /**
     * 用户定时检测
     *
     * @return bool
     */
    public function check();

    /**
     * 发送异步请求
     *
     * @param string $method 指令
     * @param array $arguments 指令参数
     * @return array|bool|mixed
     */
    public function handle(string $method, array $arguments);

    /**
     * 检测可用的连接池
     *
     * @return $this
     */
    public function startCheck();
}
