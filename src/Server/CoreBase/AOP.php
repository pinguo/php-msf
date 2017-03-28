<?php
/**
 * @desc: AOP包装器
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/28
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\CoreBase;

class AOP
{
    private $instance;

    private $onBeforeFunc = [];
    private $onAfterFunc = [];

    private $data = [];

    public function __construct($instance)
    {
        $this->instance = $instance;
    }

    public function __call($method, $arguments)
    {
        if (!method_exists($this->instance, $method)) {
            throw new SwooleException('undefined method: ' . $method);
        }

        $this->data['method'] = $method;
        $this->data['arguments'] = $arguments;

        foreach ($this->onBeforeFunc as $func) {
            $this->data = call_user_func_array($func, $this->data);
        }

        $this->data['result'] = call_user_func_array([$this->instance, $this->data['method']],
            $this->data['arguments']);

        foreach ($this->onAfterFunc as $func) {
            $this->data = call_user_func_array($func, $this->data);
        }

        return $this->data;
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
