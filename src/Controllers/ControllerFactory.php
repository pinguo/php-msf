<?php
/**
 * 控制器工厂模式
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

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

        $className = $controller;
        do {
            if (class_exists($className)) {
                break;
            }

            $className = "\\App\\Controllers\\$controller";
            if (class_exists($className)) {
                break;
            }

            $className = "\\PG\\MSF\\Controllers\\$controller";
            if (class_exists($className)) {
                break;
            }

            return null;
        } while (0);

        $controllers = $this->pool[$className] ?? null;
        if ($controllers == null) {
            $controllers = $this->pool[$className] = new \SplStack();
        }

        if (!$controllers->isEmpty()) {
            $controllerInstance = $controllers->shift();
            $controllerInstance->reUse();
            $controllerInstance->useCount++;
            return $controllerInstance;
        }

        $controllerInstance = new $className;
        $controllerInstance->coreName = $className;
        $controllerInstance->afterConstruct();
        $controllerInstance->genTime  = time();
        $controllerInstance->useCount = 1;

        return $controllerInstance;
    }

    /**
     * 获取一个Console Controller
     * @param $controller string
     * @return Controller
     */
    public function getConsoleController($controller)
    {
        if ($controller == null) {
            return null;
        }

        $className = $controller;
        do {
            if (class_exists($className)) {
                break;
            }

            $className = "\\App\\Console\\$controller";
            if (class_exists($className)) {
                break;
            }

            $className = "\\PG\\MSF\\Controllers\\$controller";
            if (class_exists($className)) {
                break;
            }

            return null;
        } while (0);

        $controllers = $this->pool[$className] ?? null;
        if ($controllers == null) {
            $controllers = $this->pool[$className] = new \SplStack();
        }

        if (!$controllers->isEmpty()) {
            $controllerInstance = $controllers->shift();
            $controllerInstance->reUse();
            $controllerInstance->useCount++;
            return $controllerInstance;
        }

        $controllerInstance = new $className;
        $controllerInstance->coreName = $className;
        $controllerInstance->afterConstruct();
        $controllerInstance->genTime  = time();
        $controllerInstance->useCount = 1;

        return $controllerInstance;
    }

    /**
     * 归还一个controller
     * @param $controller Controller
     */
    public function revertController(&$controller)
    {
        if (!$controller->getIsDestroy()) {
            $controller->destroy();
        }

        $this->pool[$controller->coreName]->push($controller);
    }
}
