<?php
/**
 * 基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\MSF\Helpers\Context;
use PG\AOP\MI;

class Child
{
    // use property and method insert
    use MI;

    /**
     * @var array 反射类属性的默认值
     */
    protected static $reflections = [];

    /**
     * 设置上下文
     *
     * @param Context $context
     * @return $this
     */
    public function setContext($context)
    {
        $this->context = $context;
        return $this;
    }

    /**
     * 销毁,解除引用
     */
    public function destroy()
    {
        if (!empty(Child::$reflections[static::class])) {
            $this->resetProperties(Child::$reflections[static::class]);
        }
    }
}
