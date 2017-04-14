<?php
/**
 * 控制器工厂模式
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

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
        $controller = ltrim($controller, '\\');
        $controllers = $this->pool[$controller]??null;
        if ($controllers == null) {
            $controllers = $this->pool[$controller] = new \SplQueue();
        }
        if (!$controllers->isEmpty()) {
            $controllerInstance = $controllers->shift();
            $controllerInstance->reUse();
            return $controllerInstance;
        }
        $className = "\\App\\Controllers\\$controller";
        if (class_exists($className)) {
            $controllerInstance = new $className;
            $controllerInstance->coreName = $controller;
            $controllerInstance->afterConstruct();
            return $controllerInstance;
        }

        $className = "\\PG\\MSF\\Server\\Controllers\\$controller";
        if (class_exists($className)) {
            $controllerInstance = new $className;
            $controllerInstance->coreName = $controller;
            $controllerInstance->afterConstruct();
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
        $this->pool[$controller->coreName]->push($controller);
    }
}