<?php
/**
 * 内核基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use Monolog\Logger;
use Noodlehaus\Config;
use PG\MSF\Pack\IPack;

class Core extends Child
{
    /**
     * @var int
     */
    public $useCount;
    /**
     * @var int
     */
    public $genTime;
    /**
     * 销毁标志
     * @var bool
     */
    public $isDestroy = false;

    /**
     * @var Loader
     */
    public $loader;
    /**
     * @var Logger
     */
    public $logger;
    /**
     * @var \swoole_server
     */
    public $server;
    /**
     * @var Config
     */
    public $config;
    /**
     * @var IPack
     */
    public $pack;

    protected $start_run_time;

    /**
     * @var null
     */
    public static $stdClass = null;

    /**
     * Task constructor.
     */
    public function __construct()
    {
        if (!empty(getInstance())) {
            $this->loader = getInstance()->loader;
            $this->logger = getInstance()->log;
            $this->server = getInstance()->server;
            $this->config = getInstance()->config;
            $this->pack = getInstance()->pack;
        }
    }

    /**
     * 销毁，解除引用
     */
    public function destroy()
    {
        parent::destroy();
        $this->isDestroy = true;
    }

    /**
     * 对象复用
     */
    public function reUse()
    {
        $this->isDestroy = false;
    }
}