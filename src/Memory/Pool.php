<?php
/**
 * @desc: 对象复用池
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/7
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Memory;

use Exception;

class Pool
{
    /**
     * 对象池实现
     *
     * @var Pool
     */
    private static $instance;

    /**
     * 所有内存中的对象，根据类名区分
     *
     * @var array
     */
    public $map;

    /**
     * 当前待创建对象的源对象
     *
     * @var mixed
     */
    private $__currentObjParent;

    /**
     * 当前待创建对象的源对象的父对象
     */
    private $__currentObjRoot;

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
     * 设置当前待创建对象的源对象
     *
     * @param $obj
     * @return $this
     */
    public function setCurrentObjParent($obj)
    {
        if (is_object($this->__currentObjParent)) {
            $this->__currentObjRoot = $this->__currentObjParent;
        }

        if (is_null($obj)) {
            $this->__currentObjRoot = null;
        }

        $this->__currentObjParent = $obj;
        return $this;
    }

    /**
     * 获取当前待创建对象的源对象
     *
     * @return mixed
     */
    public function getCurrentObjParent()
    {
        return $this->__currentObjParent;
    }

    /**
     * 获取一个
     * @param string $class
     * @return mixed
     */
    public function get($class)
    {
        $poolName = trim($class, '\\');
        if (is_object($this->__currentObjRoot) && $poolName == get_class($this->__currentObjRoot)) {
            return $this->__currentObjRoot;
        }

        $pool     = $this->map[$poolName] ?? null;
        if ($pool == null) {
            $pool = $this->applyNewPool($poolName);
        }

        if ($pool->count()) {
            $obj = $pool->shift();
            $obj->isContruct = false;
            return $obj;
        } else {
            $reflector         = new \ReflectionClass($poolName);
            $obj               = $reflector->newInstanceWithoutConstructor();
            $obj->__useCount   = 0;
            $obj->__genTime    = time();
            $obj->__isContruct = false;
            unset($reflector);
            return $obj;
        }
    }

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
     * @param $classInstance
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
