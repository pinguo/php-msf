<?php
namespace Server\CoreBase;
/**
 * Model工厂模式
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午12:03
 */
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
            $model_instance = array_pop($this->pool[$model]);
            $model_instance->reUse();
            return $model_instance;
        }
        $class_name = "\\app\\Models\\$model";
        if (class_exists($class_name)) {
            $model_instance = new $class_name;
        } else {
            $class_name = "\\Server\\Models\\$model";
            if (class_exists($class_name)) {
                $model_instance = new $class_name;
            } else {
                throw new SwooleException("class $model is not exist");
            }
        }
        $model_instance->core_name = $model;
        return $model_instance;
    }

    /**
     * 归还一个model
     * @param $model Model
     */
    public function revertModel($model)
    {
        if (!$model->is_destroy) {
            $model->destroy();
        }
        $this->pool[$model->core_name][] = $model;
    }
}