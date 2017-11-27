<?php
/**
 * 框架定义的系统级常量
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

/**
 * Class Macro
 * @package PG\MSF
 */

//修复单词错误，但是要兼容错误的单词
class_alias(__NAMESPACE__ . '\\Macro', __NAMESPACE__ . '\\Marco');

class Macro
{
    /**
     * 分布式模式
     */
    const CLUSTER                                   = 0;

    /**
     * 主从模式
     */
    const MASTER_SLAVE                              = 1;

    /**
     * Server统计key
     */
    const SERVER_STATS                              = 'msf_server_stats_';

    /**
     * 不进行序列化
     */
    const SERIALIZE_NONE                            = 0;

    /**
     * PHP serialize
     */
    const SERIALIZE_PHP                             = 1;

    /**
     * PHP IGBINARY
     */
    const SERIALIZE_IGBINARY                        = 2;

    /**
     * Task任务
     */
    const SERVER_TYPE_TASK                          = 500;

    /**
     * TCP请求
     */
    const TCP_REQUEST                               = 'TCP';

    /**
     * HTTP请求
     */
    const HTTP_REQUEST                              = 'HTTP';

    /**
     * 不销毁成员变量资源
     */
    const DS_NONE                                   = 0;

    /**
     * 销毁PUBLIC成员变量资源，默认
     */
    const DS_PUBLIC                                 = 1<<0;

    /**
     * 销毁PROTECTED成员变量资源
     */
    const DS_PROTECTED                              = 1<<1;

    /**
     * 销毁PRIVATE成员变量资源
     */
    const DS_PRIVATE                                = 1<<2;

    /**
     * 销毁STATIC成员变量资源
     */
    const DS_STATIC                                 = 1<<3;

    /**
     * 进程为WORKER
     */
    const PROCESS_WORKER                            = 1;

    /**
     * 进程为TASKER
     */
    const PROCESS_TASKER                            = 2;

    /**
     * 进程为RELOAD
     */
    const PROCESS_RELOAD                            = 3;

    /**
     * 进程为CONFIG
     */
    const PROCESS_CONFIG                            = 4;

    /**
     * 进程为TIMER
     */
    const PROCESS_TIMER                             = 5;

    /**
     * 进程为MASTER
     */
    const PROCESS_MASTER                            = 4094;

    /**
     * 进程为MANAGER
     */
    const PROCESS_MANAGER                           = 4095;

    /**
     * 进程为USER（默认）
     */
    const PROCESS_USER                              = 4096;

    /**
     * 进程名称
     */
    const PROCESS_NAME                              = [
        self::PROCESS_MASTER                        => 'Master',
        self::PROCESS_MANAGER                       => 'Manager',
        self::PROCESS_WORKER                        => 'Worker',
        self::PROCESS_TASKER                        => 'Tasker',
        self::PROCESS_RELOAD                        => 'Reload',
        self::PROCESS_CONFIG                        => 'Config',
        self::PROCESS_TIMER                         => 'Timer',
        self::PROCESS_USER                          => 'User',
    ];

    /**
     * 发送静态文件404
     */
    const SEND_FILE_404                             = 404;

    /**
     * 发送静态文件200
     */
    const SEND_FILE_200                             = 200;

    /**
     * 发送静态文件304
     */
    const SEND_FILE_304                             = 304;

    /**
     * 发送静态文件403
     */
    const SEND_FILE_403                             = 403;

    /**
     * Timer TICK
     */
    const SWOOLE_TIMER_TICK                          = 'swoole_timer_tick';

    /**
     * Timer AFTER
     */
    const SWOOLE_TIMER_AFTER                         = 'swoole_timer_after';

    /**
     * 文件Session
     */
    const SESSION_FILE                               = 1;
    /**
     * redis Session
     */
    const SESSION_REDIS                              = 2;
    /**
     * redis proxy Session
     */
    const SESSION_REDIS_PROXY                        = 3;
}
