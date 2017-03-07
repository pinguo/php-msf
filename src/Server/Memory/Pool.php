<?php
/**
 * @desc: 对象复用池
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/7
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Memory;

use PG\MSF\Server\CoreBase\SwooleException;

class Pool
{
    private static $instance;
    private $map;

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
        $pool = $this->map[$class] ?? null;
        if ($pool == null) {
            $pool = $this->applyNewPool($class);
        }
        if ($pool->count()) {
            return $pool->shift();
        } else {
            return new $class;
        }
    }

    private function applyNewPool($class)
    {
        if (array_key_exists($class, $this->map)) {
            throw new SwooleException('the name is exists in pool map');
        }
        $this->map[$class] = new \SplStack();
        return $this->map[$class];
    }

    /**
     * 返还一个对象
     * @param $classInstance
     */
    public function push($classInstance)
    {
        $class = get_class($classInstance);
        $pool = $this->map[$class] ?? null;
        if ($pool == null) {
            $pool = $this->applyNewPool($class);
        }
        $pool->push($classInstance);
    }
}
