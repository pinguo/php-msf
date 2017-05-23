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
use PG\AOP\MI;
use PG\MSF\Pack\IPack;

class Core extends Child
{
    // use method insert
    use MI;

    /**
     * @var array
     */
    protected static $reflections = [];
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

    public function __sleep()
    {
        return ['useCount', 'genTime'];
    }

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
            $this->pack   = getInstance()->pack;
        }

        if (empty(static::$reflections)) {
            $reflection = new \ReflectionClass(static::class);
            $default    = $reflection->getDefaultProperties();
            $ps         = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_STATIC);

            foreach ($ps as $val) {
                unset($default[$val->getName()]);
            }
            unset($default['useCount']);
            unset($default['genTime']);
            unset($default['coreName']);
            unset($default['loader']);
            unset($default['logger']);
            unset($default['server']);
            unset($default['config']);
            unset($default['pack']);
            unset($default['isDestroy']);
            unset($default['isConstruct']);
            static::$reflections = $default;
        }
    }

    /**
     * 销毁,解除引用
     */
    public function destroy()
    {
        if (!$this->isDestroy) {
            parent::destroy();
            $this->isDestroy = true;
            $this->resetProperties(static::$reflections);
        }
    }

    /**
     * 对象复用
     */
    public function reUse()
    {
        $this->isDestroy = false;
    }
}
