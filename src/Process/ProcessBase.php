<?php
/**
 * 自定义进程抽象类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Process;

use Noodlehaus\Config as Conf;
use PG\MSF\Marco;
use PG\MSF\MSFServer;

abstract class ProcessBase
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
        $this->config                 = $config;
        $this->MSFServer              = $MSFServer;
        $this->MSFServer->processType = Marco::PROCESS_USER;
    }
}