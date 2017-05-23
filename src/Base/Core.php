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
    protected $isDestroy = false;
    /**
     * @var Loader
     */
    protected $loader;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var \swoole_server
     */
    protected $server;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var IPack
     */
    protected $pack;

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
            $reflection  = new \ReflectionClass(static::class);
            $default     = $reflection->getDefaultProperties();
            $ps          = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            $ss          = $reflection->getProperties(\ReflectionProperty::IS_STATIC);
            $autoDestroy = [];
            foreach ($ps as $val) {
                $autoDestroy[$val->getName()] = $default[$val->getName()];
            }
            foreach ($ss as $val) {
                unset($autoDestroy[$val->getName()]);
            }
            unset($autoDestroy['useCount']);
            unset($autoDestroy['genTime']);
            unset($autoDestroy['coreName']);
            static::$reflections = $autoDestroy;
            unset($reflection);
            unset($default);
            unset($ps);
            unset($ss);
        }
    }

    /**
     * @return Loader
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * @return Logger|\PG\Log\PGLog
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return \swoole_server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return IPack
     */
    public function getPack()
    {
        return $this->pack;
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

    public function getIsDestroy()
    {
        return $this->isDestroy;
    }
}
