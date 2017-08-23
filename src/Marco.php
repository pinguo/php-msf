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

    /**
     * 不销毁成员变量资源
     */
    const DS_NONE = 0b0000;

    /**
     * 销毁PUBLIC成员变量资源，默认
     */
    const DS_PUBLIC = 0b0001;

    /**
     * 销毁PROTECTED成员变量资源
     */
    const DS_PROTECTED = 0b0010;

    /**
     * 销毁PRIVATE成员变量资源
     */
    const DS_PRIVATE = 0b0100;

    /**
     * 销毁STATIC成员变量资源
     */
    const DS_STATIC = 0b1000;
}
