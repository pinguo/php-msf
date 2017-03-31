<?php
/**
 * Model工厂模式
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

class ModelFactory
{
    /**
     * @var ModelFactory
     */
    private static $instance;
    private $pool = [];

    /**
     * ModelFactory constructor.
     */
    public function __construct()
    {
        self::$instance = $this;
    }

    /**
     * 获取单例
     * @return ModelFactory
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            new ModelFactory();
        }
        return self::$instance;
    }

    /**
     * 获取一个model
     * @param $model
     * @return mixed
     * @throws SwooleException
     */
    public function getModel($model)
    {
        $model = str_replace('/', '\\', $model);
        if (!key_exists($model, $this->pool)) {
            $this->pool[$model] = [];
        }
        if (count($this->pool[$model]) > 0) {
            $modelInstance = array_pop($this->pool[$model]);
            $modelInstance->reUse();
            return $modelInstance;
        }
        $class_name = "\\App\\Models\\$model";
        if (class_exists($class_name)) {
            $modelInstance = new $class_name;
            $modelInstance->coreName = $model;
            $modelInstance->afterConstruct();
        } else {
            $class_name = "\\PG\\MSF\\Server\\Models\\$model";
            if (class_exists($class_name)) {
                $modelInstance = new $class_name;
                $modelInstance->coreName = $model;
                $modelInstance->afterConstruct();
            } else {
                throw new SwooleException("class $model is not exist");
            }
        }
        return $modelInstance;
    }

    /**
     * 归还一个model
     * @param $model Model
     */
    public function revertModel($model)
    {
        if (!$model->isDestroy) {
            $model->destroy();
        }
        $this->pool[$model->coreName][] = $model;
    }
}