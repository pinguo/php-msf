<?php
/**
 * 上下文实体对象
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Helpers;

use PG\Context\AbstractContext;
use PG\MSF\Base\Input;
use PG\MSF\Base\Output;
use PG\MSF\Base\Pool;
use PG\AOP\MI;

/**
 * Class Context
 * @package PG\MSF\Helpers
 */
class Context extends AbstractContext
{
    use MI;
    
    /**
     * @var Input 请求输入对象
     */
    protected $input;

    /**
     * @var Output 请求响应对象
     */
    protected $output;

    /**
     * @var Pool 对象池对象
     */
    protected $objectPool;

    /**
     * @var string 执行的控制器名称
     */
    protected $controllerName;

    /**
     * @var string 执行的方法名称
     */
    protected $actionName;

    /**
     * @var array 存储自定义的全局上下文数据
     */
    protected $userDefined = [];

    /**
     * @var int 当前请求ID
     */
    protected $requestId;

    /**
     * Context constructor.
     *
     * @param $requestId int 请求ID
     */
    public function __construct($requestId)
    {
        $this->requestId = $requestId;
    }

    /**
     * 返回当前请求ID
     *
     * @return int
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

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
     * @param Input $input 请求对象
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
     * @param Output $output 请求输出对象
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
     * @param Pool $objectPool 对象池实例
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
     * @param string $controllerName 控制器名称
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
     * @param string $actionName 控制器方法名
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
     * @return string
     */
    public function getActionName()
    {
        return $this->actionName;
    }

    /**
     * 获取所有用户自定义的全局上下文对象
     *
     * @return array
     */
    public function getAllUserDefined()
    {
        return $this->userDefined;
    }

    /**
     * 获取key所对应的用户自定义的全局上下文数据
     *
     * @param string $key 用户自定义的上下文数据Key
     * @return mixed|null
     */
    public function getUserDefined($key)
    {
        return $this->userDefined[$key] ?? null;
    }

    /**
     * 设置key所对应的用户自定义的全局上下文的value
     *
     * @param string $key 用户自定义的上下文数据Key
     * @param mixed $val 用户自定义的上下文数据Value
     * @return $this
     */
    public function setUserDefined($key, $val)
    {
        $this->userDefined[(string)$key] = $val;
        return $this;
    }

    /**
     * 属性不用于序列化
     *
     * @return array
     */
    public function __sleep()
    {
        return ['logId', 'requestId', 'input', 'controllerName', 'actionName', 'userDefined'];
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        $this->PGLog          = null;
        $this->input          = null;
        $this->output         = null;
        $this->objectPool     = null;
        $this->controllerName = null;
        $this->actionName     = null;
        $this->userDefined    = [];
        $this->requestId      = null;
    }
}
