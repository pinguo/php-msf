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
    protected $isDestroy = false;

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
        if (empty(Child::$reflections[static::class])) {
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
            Child::$reflections[static::class] = $autoDestroy;
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
        return getInstance()->loader;
    }

    /**
     * @return \swoole_server
     */
    public function getServer()
    {
        return getInstance()->server;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return getInstance()->config;
    }

    /**
     * @return IPack
     */
    public function getPack()
    {
        return getInstance()->pack;
    }

    /**
     * 销毁,解除引用
     */
    public function destroy()
    {
        if (!$this->isDestroy) {
            parent::destroy();
            $this->isDestroy = true;
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
