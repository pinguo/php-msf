<?php
/**
 * 控制器工厂模式
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

class ControllerFactory
{
    /**
     * @var ControllerFactory
     */
    private static $instance;
    public $pool = [];

    /**
     * ControllerFactory constructor.
     */
    public function __construct()
    {
        self::$instance = $this;
    }

    /**
     * 获取单例
     * @return ControllerFactory
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            new ControllerFactory();
        }
        return self::$instance;
    }

    /**
     * 获取一个Controller
     * @param $controller string
     * @return Controller
     */
    public function getController($controller)
    {
        if ($controller == null) {
            return null;
        }
        $controller  = ltrim($controller, '\\');
        $className = "\\App\\Controllers\\$controller";

        if (class_exists($className)) {
            $controllers = $this->pool[$controller]??null;
            if ($controllers == null) {
                $controllers = $this->pool[$controller] = new \SplStack();
            }

            if (!$controllers->isEmpty()) {
                $controllerInstance = $controllers->shift();
                $controllerInstance->reUse();
                $controllerInstance->useCount++;
                return $controllerInstance;
            }

            $controllerInstance = new $className;
            $controllerInstance->coreName = $controller;
            $controllerInstance->afterConstruct();
            $controllerInstance->genTime  = time();
            $controllerInstance->useCount = 1;
            return $controllerInstance;
        }

        $className = "\\PG\\MSF\\Controllers\\$controller";
        if (class_exists($className)) {
            $controllers = $this->pool[$controller]??null;
            if ($controllers == null) {
                $controllers = $this->pool[$controller] = new \SplStack();
            }

            if (!$controllers->isEmpty()) {
                $controllerInstance = $controllers->shift();
                $controllerInstance->reUse();
                $controllerInstance->useCount++;
                return $controllerInstance;
            }

            $controllerInstance = new $className;
            $controllerInstance->coreName = $controller;
            $controllerInstance->afterConstruct();
            $controllerInstance->genTime  = time();
            $controllerInstance->useCount = 1;
            return $controllerInstance;
        }

        return null;
    }

    /**
     * 归还一个controller
     * @param $controller Controller
     */
    public function revertController($controller)
    {
        if (!$controller->isDestroy) {
            $controller->destroy();
        }


        //判断是否还返还对象：使用时间超过2小时或者使用次数大于10000则不返还，直接销毁
        if (($controller->genTime + 7200) < time() || $controller->useCount > 10000) {
            unset($controller);
        } else {
            $this->pool[$controller->coreName]->push($controller);
        }
    }
}