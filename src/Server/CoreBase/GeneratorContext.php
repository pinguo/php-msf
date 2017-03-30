<?php
/**
 * 生成器的上下文,记录协程运行过程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

class GeneratorContext
{
    /**
     * @var Controller
     */
    protected $controller;
    protected $controllerName;
    protected $methodName;
    protected $stack;

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
        $i = count($this->stack);
        $this->stack[] = "| #第 $i 层嵌套出错在第 $number 个yield后";
        
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
        $this->stack[] = "| #出错文件: $file($line)";
    }

    /**
     * @param $message
     */
    public function setErrorMessage($message)
    {
        $this->stack[] = "| #报错消息: $message";
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
        $this->stack[] = "| #目标函数： $controllerName -> $methodName";
    }

    /**
     * 获取堆打印
     */
    public function getTraceStack()
    {
        $trace = "协程错误指南: \n";
        for ($i = 0; $i < count($this->stack); $i++) {
            $trace .= "{$this->stack[$i]}\n";
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