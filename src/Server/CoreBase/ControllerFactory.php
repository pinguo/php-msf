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
     * @param $isRpc bool 是否为 rpc 请求，inner 请求也将标记为 rpc
     * @return Controller
     */
    public function getController($controller, $isRpc = false)
    {
        if ($controller == null) {
            return null;
        }
        $pureController = $controller;
        if ($isRpc) {
            $controller = 'Handlers/' . $controller;
        }
        $controllers = $this->pool[$controller]??null;
        if ($controllers == null) {
            $controllers = $this->pool[$controller] = new \SplQueue();
        }
        if (!$controllers->isEmpty()) {
            $controllerInstance = $controllers->shift();
            $controllerInstance->reUse();
            return $controllerInstance;
        }
        if ($isRpc) {
            $class_name = "\\App\\Handlers\\$pureController";
        } else {
            $class_name = "\\App\\Controllers\\$controller";
        }
        if (class_exists($class_name)) {
            $controllerInstance = new $class_name;
            $controllerInstance->coreName = $controller;
            $controllerInstance->afterConstruct();
            return $controllerInstance;
        }
        if ($isRpc) {
            $class_name = "\\PG\\MSF\\Server\\Handlers\\$pureController";
        } else {
            $class_name = "\\PG\\MSF\\Server\\Controllers\\$controller";
        }
        if (class_exists($class_name)) {
            $controllerInstance = new $class_name;
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