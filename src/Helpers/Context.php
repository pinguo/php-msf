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
use PG\MSF\{
    Base\Input, Base\Output, Memory\Pool
};

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
     * 获取请求输入对象
     *
     * @return Input
     */
    public function getInput()
    {
        return $this->input ?? null;
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
        return $this->output ?? null;
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
        return $this->objectPool ?? null;
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

    public function __sleep()
    {
        return ['logId', 'input'];
    }

    public function destroy()
    {
        unset($this->PGLog);
        unset($this->input);
        unset($this->output);
        unset($this->objectPool);
    }
}