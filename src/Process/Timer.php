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
use PG\MSF\Macro;

/**
 * Class Timer
 * @package PG\MSF\Process
 */
class Timer extends ProcessBase
{
    /**
     * Timer constructor.
     *
     * @param Conf $config 配置对象
     * @param MSFServer $MSFServer Server运行实例
     */
    public function __construct(Conf $config, MSFServer $MSFServer)
    {
        parent::__construct($config, $MSFServer);
        $this->MSFServer->processType = Macro::PROCESS_TIMER;
        writeln('User      Timer: Enabled');
    }
}
