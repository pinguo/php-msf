<?php

/**
 * @desc: 上下文实体对象
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/17
 * @copyright All rights reserved.
 */

namespace PG\MSF\Helpers;

use PG\Context\AbstractContext;
use PG\Log\PGLog;
use PG\MSF\Base\Input;
use PG\MSF\Base\Output;
use PG\MSF\Memory\Pool;

class Context extends AbstractContext
{
    /**
     * @var Input
     */
    protected $input;

    /**
     * @var Output
     */
    protected $output;

    /**
     * 对象池对象
     *
     * @var Pool
     */
    protected $objectPool;

    /**
     * 执行的控制器名称
     *
     * @var string
     */
    protected $controllerName;

    /**
     * 执行的方法名称
     *
     * @var string
     */
    protected $actionName;

    /**
     * 获取请求输入对象
     *
     * @return Input
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * 设置请求输入对象
     *
     * @param $input
     * @return $this
     */
    public function setInput($input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * 获取请求输出对象
     *
     * @return Output
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * 设置请求输出对象
     *
     * @param $output
     * @return $this
     */
    public function setOutput($output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * 获取对象池对象
     *
     * @return Pool
     */
    public function getObjectPool()
    {
        return $this->objectPool;
    }

    /**
     * 设置对象池对象
     *
     * @param $objectPool
     * @return $this
     */
    public function setObjectPool($objectPool)
    {
        $this->objectPool = $objectPool;
        return $this;
    }

    /**
     * 设置控制器名称
     *
     * @param $controllerName
     * @return $this
     */
    public function setControllerName($controllerName)
    {
        $this->controllerName = $controllerName;
        return $this;
    }

    /**
     * 返回控制器名称
     *
     * @return string
     */
    public function getControllerName()
    {
        return $this->controllerName;
    }

    /**
     * 设置方法名称
     *
     * @param $actionName
     * @return $this
     */
    public function setActionName($actionName)
    {
        $this->actionName = $actionName;
        return $this;
    }

    /**
     * 返回方法名称
     *
     * @return $this
     */
    public function getActionName()
    {
        return $this->actionName;
    }

    public function __sleep()
    {
        return ['logId', 'input', 'controllerName', 'actionName'];
    }

    public function destroy()
    {
        // todo
    }
}
