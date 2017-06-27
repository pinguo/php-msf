<?php
/**
 * @desc: proxy handle interface
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/11
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

interface IProxy
{
    public function check();

    public function handle(string $method, array $arguments);

    public function startCheck();
}
