<?php
/**
 * 框架定义的系统级常量
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

class Marco
{
    /**
     * Redis分布式模式
     */
    const CLUSTER = 0;

    /**
     * Redis主从模式
     */
    const MASTER_SLAVE = 1;

    /**
     * Server统计key
     */
    const SERVER_STATS = 'msf_server_stats_';

    /**
     * 不进行序列化
     */
    const SERIALIZE_NONE = 0;

    /**
     * PHP serialize
     */
    const SERIALIZE_PHP = 1;

    /**
     * PHP IGBINARY
     */
    const SERIALIZE_IGBINARY = 2;

    /**
     * Task任务
     */
    const SERVER_TYPE_TASK = 500;

    /**
     * TCP请求
     */
    const TCP_REQUEST  = 'TCP';
    /**
     * HTTP请求
     */
    const HTTP_REQUEST = 'HTTP';
}
