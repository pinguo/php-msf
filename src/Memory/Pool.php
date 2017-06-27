<?php
/**
 * @desc: 对象复用池
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/7
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Memory;

use PG\MSF\Base\Exception;

class Pool
{
    private static $instance;
    public $map;

    private function __construct()
    {
        $this->map = [];
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Pool();
        }
        return self::$instance;
    }

    /**
     * 获取一个
     * @param $class
     * @return mixed
     */
    public function get($class)
    {
        $poolName = trim($class, '\\');
        $pool     = $this->map[$poolName] ?? null;
        if ($pool == null) {
            $pool = $this->applyNewPool($poolName);
        }
        if ($pool->count()) {
            return $pool->shift();
        } else {
            $obj = new $class;
            $obj->useCount = 0;
            $obj->genTime = time();
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
