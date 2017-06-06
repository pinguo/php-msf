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
    // use method insert
    use MI;
    /**
     * @var array
     */
    protected static $reflections = [];
    /**
     * 名称
     * @var string
     */
    public $coreName;

    /**
     * 子集
     *
     * @var array
     */
    public $childList = [];

    /**
     * 判断是否执行了__construct
     */
    protected $isConstruct = false;

    /**
     * after constructor
     */
    public function afterConstruct()
    {
        $this->isConstruct = true;
    }

    public function getIsConstruct()
    {
        return $this->isConstruct;
    }

    /**
     * 加入一个插件
     *
     * @param $child Child
     * @return $this
     */
    public function addChild($child)
    {
        $child->onAddChild($this);
        $this->childList[$child->coreName] = $child;
        return $this;
    }

    /**
     * 被加入列表时
     *
     * @param $parent
     * @return $this
     */
    public function onAddChild($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * 是否存在插件
     *
     * @param $name
     * @return bool
     */
    public function hasChild($name)
    {
        return key_exists($name, $this->childList);
    }

    /**
     * 获取插件
     *
     * @param $name
     * @return mixed|null
     */
    public function getChild($name)
    {
        return $this->childList[$name] ?? null;
    }

    /**
     * 设置上下文
     *
     * @param $context
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
        foreach ($this->childList as $coreChild) {
            $coreChild->destroy();
        }
        $this->resetProperties(Child::$reflections[static::class]);
    }
}
