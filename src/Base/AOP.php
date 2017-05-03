<?php
/**
 * @desc: AOP包装器
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/28
 * @copyright All rights reserved.
 */

namespace PG\MSF\Base;

class AOP
{
    private $instance;
    private $attributes = [];

    private $onBeforeFunc = [];
    private $onAfterFunc = [];

    private $data = [];

    public function __construct($instance, $isClone = false)
    {
        $isClone && ($instance = clone $instance);
        $instance->aop = $this;
        $this->instance = $instance;
    }

    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function __call($method, $arguments)
    {
        $this->data['method'] = $method;
        $this->data['arguments'] = $arguments;
        unset($this->data['result']);

        foreach ($this->onBeforeFunc as $func) {
            $this->data = call_user_func_array($func, $this->data);
        }

        //支持提前返回结果 不需要继续调用
        if (isset($this->data['result'])) {
            return $this->data['result'];
        }

        $this->data['result'] = call_user_func_array([$this->instance, $this->data['method']],
            $this->data['arguments']);

        foreach ($this->onAfterFunc as $func) {
            $this->data = call_user_func_array($func, $this->data);
        }

        return $this->data['result'];
    }

    public function registerOnBefore(callable $callback)
    {
        $this->onBeforeFunc[] = $callback;
    }

    public function registerOnAfter(callable $callback)
    {
        $this->onAfterFunc[] = $callback;
    }

    public function registerOnBoth(callable $callback)
    {
        $this->onBeforeFunc[] = $callback;
        $this->onAfterFunc[] = $callback;
    }
}
