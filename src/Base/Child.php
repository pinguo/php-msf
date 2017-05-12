<?php
/**
 * 基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

class Child
{
    /**
     * 名称
     * @var string
     */
    public $coreName;

    /**
     * @var
     */

    public $parent;
    /**
     * 子集
     * @var array
     */
    public $childList = [];

    /**
     * 判断是否执行了__construct
     */
    public $isConstruct = false;
    /**
     * 上下文
     * @var array
     */
    public $context;

    /**
     * after constructor
     */
    public function afterConstruct()
    {
        $this->isConstruct = true;
    }


    /**
     * 加入一个插件
     * @param $child Child
     */
    public function addChild($child)
    {
        $child->onAddChild($this);
        $this->childList[$child->coreName] = $child;
    }

    /**
     * 被加入列表时
     * @param $parent
     */
    public function onAddChild($parent)
    {
        $this->parent = $parent;
    }

    /**
     * 是否存在插件
     * @param $name
     * @return bool
     */
    public function hasChild($name)
    {
        return key_exists($name, $this->childList);
    }

    /**
     * 获取插件
     * @param $name
     * @return mixed|null
     */
    public function getChild($name)
    {
        return $this->childList[$name]??null;
    }

    /**
     * 获取上下文
     * @return array
     */
    public function &getContext()
    {
        return $this->context;
    }

    /**
     * 设置上下文
     * @param $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * 销毁，解除引用
     */
    public function destroy()
    {
        foreach ($this->childList as $coreChild) {
            $coreChild->destroy();
        }
        $this->childList = [];
        unset($this->parent);
        unset($this->context);
    }

}