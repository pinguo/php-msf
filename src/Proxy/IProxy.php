<?php
/**
 * proxy handle interface
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

interface IProxy
{
    public function check();

    public function handle(string $method, array $arguments);

    public function startCheck();
}
