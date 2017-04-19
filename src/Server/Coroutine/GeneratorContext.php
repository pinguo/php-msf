<?php
/**
 * 生成器的上下文,记录协程运行过程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Coroutine;

use PG\MSF\Server\CoreBase\Controller;

class GeneratorContext
{
    /**
     * @var Controller
     */
    protected $controller;
    protected $controllerName;
    protected $methodName;
    protected $stack;
    protected $i = 0;

    public function __construct()
    {
        $this->stack = array();
    }

    /**
     * @param $number
     */
    public function addYieldStack($number)
    {
        $number++;
        $this->i++;

        $this->stack[$this->i][] = "| #第 {$this->i} 层嵌套出错在第 $number 个yield后";
    }

    /**
     * @param $number
     */
    public function setStackMessage($number)
    {
        $number++;
        $this->stack[$this->i][] = "| #第 {$this->i} 层嵌套出错在第 $number 个yield后";
    }

    /**
     *
     */
    public function popYieldStack()
    {
        array_pop($this->stack);
    }

    /**
     * @param $file
     * @param $line
     */
    public function setErrorFile($file, $line)
    {
        $this->stack[$this->i][] = "| #出错文件: $file($line)";
    }

    /**
     * @param $message
     */
    public function setErrorMessage($message)
    {
        $this->stack[$this->i][] = "| #报错消息: $message";
    }

    /**
     * @return \PG\MSF\Server\Controllers\BaseController
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param $controller
     * @param $controllerName
     * @param $methodName
     */
    public function setController($controller, $controllerName, $methodName)
    {
        $this->controller = $controller;
        $this->controllerName = $controllerName;
        $this->methodName = $methodName;
        $this->stack[$this->i][] = "| #目标函数： $controllerName -> $methodName";
    }

    /**
     * 获取堆打印
     */
    public function getTraceStack()
    {
        $trace = "协程错误指南: \n";
        foreach ($this->stack as $i => $v) {
            foreach ($v as $value) {
                $trace .= "{$value}\n";
            }
        }

        $trace = trim($trace);

        return $trace;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        unset($this->controller);
        unset($this->stack);
    }
}