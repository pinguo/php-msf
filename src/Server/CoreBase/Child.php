<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-29
 * Time: 上午11:05
 */

namespace Server\CoreBase;


class Child
{
    /**
     * 名称
     * @var string
     */
    public $core_name;

    /**
     * @var
     */

    public $parent;
    /**
     * 子集
     * @var array
     */
    public $child_list = [];

    /**
     * 加入一个插件
     * @param $child Child
     */
    public function addChild($child)
    {
        $child->onAddChild($this);
        $this->child_list[$child->core_name] = $child;
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
        return key_exists($name, $this->child_list);
    }

    /**
     * 获取插件
     * @param $name
     * @return mixed|null
     */
    public function getChild($name)
    {
        return $this->child_list[$name]??null;
    }

    /**
     * 销毁，解除引用
     */
    public function destroy()
    {
        foreach ($this->child_list as $core_child) {
            $core_child->destroy();
        }
        $this->child_list = [];
        unset($this->parent);
    }

}