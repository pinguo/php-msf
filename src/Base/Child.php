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
     * 添加树的节点
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
     * 添加节点触发的事件
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
     * 某一个类是否在树上已经存在
     *
     * @param $name
     * @return bool
     */
    public function hasChild($name)
    {
        return key_exists($name, $this->childList);
    }

    /**
     * 获取树的节点
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
