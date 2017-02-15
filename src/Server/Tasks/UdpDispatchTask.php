<?php
/**
 * UdpDispatchTask
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Tasks;

use PG\MSF\Server\CoreBase\Task;

class UdpDispatchTask extends Task
{
    public function send()
    {
        //广播
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
        socket_connect($sock, "255.255.255.255", $this->config['dispatch_server']['port']);
        $buf = $this->config['dispatch_server']['password'];
        socket_write($sock, $buf, strlen($buf));
        socket_close($sock);
    }
}