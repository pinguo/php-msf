<?php
/**
 * 自定义业务定时器
 *
 * 本定时器目标不是取代Linux Crontab，因为Crontab运行得足够好，本定时器解决一些业务计数需要定时清理的工作
 * 注意本进程为同步阻塞模式，不支持协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Process;

use Noodlehaus\Config as Conf;
use PG\MSF\MSFServer;

class Timer
{
    /**
     * @var Conf Server运行实例配置对象
     */
    public $config;

    /**
     * @var MSFServer 运行的Server实例
     */
    public $MSFServer;

    /**
     * Timer constructor.
     *
     * @param Conf $config
     * @param MSFServer $MSFServer
     */
    public function __construct(Conf $config, MSFServer $MSFServer)
    {
        echo 'User      Timer: Enable', "\n";
        $MSFServer->onInitTimer();
    }
}