<?php
/**
 * 对象池
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\AOP\Wrapper;
use PG\MSF\Macro;

/**
 * Class Pool
 * @package PG\MSF\Base
 */
class Pool
{
    /**
     * @var Pool 对象池实现
     */
    private static $instance;

    /**
     * @var Wrapper AOP包装器
     */
    public $__wrapper;

    /**
     * @var array 所有内存中的对象，根据类名区分
     */
    public $map;

    /**
     * Pool constructor.
     */
    private function __construct()
    {
        $this->map = [];
    }

    /**
     * 产生对象池单例
     *
     * @return Pool
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Pool();
        }
        return self::$instance;
    }

    /**
     * 获取一个
     *
     * @param string $class 完全命名空间类名
     * @param array $args 可变参数列表
     * @return mixed
     */
    public function get($class, ...$args)
    {
        $poolName = trim($class, '\\');

        if (!$poolName) {
            return null;
        }

        $pool     = $this->map[$poolName] ?? null;
        if ($pool == null) {
            $pool = $this->applyNewPool($poolName);
        }

        if ($pool->count()) {
            $obj = $pool->shift();
            $obj->__isConstruct = false;
            return $obj;
        } else {
            $reflector         = new \ReflectionClass($poolName);
            $obj               = $reflector->newInstanceWithoutConstructor();
            $obj->__useCount   = 0;
            $obj->__genTime    = time();
            $obj->__isConstruct = false;
            $obj->__DSLevel    = Macro::DS_PUBLIC;
            unset($reflector);
            return $obj;
        }
    }

    /**
     * 创建新的栈，用于储存相应的对象
     *
     * @param string $poolName 对象池名称
     * @return mixed
     * @throws Exception
     */
    private function applyNewPool($poolName)
    {
        if (array_key_exists($poolName, $this->map)) {
            throw new Exception('the name is exists in pool map');
        }
        $this->map[$poolName] = new \SplStack();

        return $this->map[$poolName];
    }

    /**
     * 返还一个对象
     *
     * @param mixed $classInstance 对象实例
     */
    public function push($classInstance)
    {
        $class = trim(get_class($classInstance), '\\');
        $pool = $this->map[$class] ?? null;
        if ($pool == null) {
            $pool = $this->applyNewPool($class);
        }
        $pool->push($classInstance);
    }
}
