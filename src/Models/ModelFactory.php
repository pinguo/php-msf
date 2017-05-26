<?php
/**
 * Model工厂模式
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Models;

use PG\MSF\Base\Exception;

class ModelFactory
{
    /**
     * @var ModelFactory
     */
    private static $instance;
    public $pool = [];

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
     * @throws Exception
     */
    public function getModel($model)
    {
        $className = $model;
        do {
            if (class_exists($className)) {
                break;
            }

            $className = "\\App\\Models\\$model";
            if (class_exists($className)) {
                break;
            }

            $className = "\\PG\\MSF\\Models\\$model";
            if (class_exists($className)) {
                break;
            }

            throw new Exception("class $model is not exist");
        } while (0);

        $models = $this->pool[$model]??null;
        if ($models == null) {
            $models = $this->pool[$model] = new \SplStack();
        }

        if (!$models->isEmpty()) {
            $modelInstance = $models->shift();
            $modelInstance->reUse();
            $modelInstance->useCount++;
            return $modelInstance;
        }

        $modelInstance = new $className;
        $modelInstance->coreName = $model;
        $modelInstance->afterConstruct();
        $modelInstance->genTime  = time();
        $modelInstance->useCount = 1;

        return $modelInstance;
    }

    /**
     * 归还一个model
     * @param $model Model
     */
    public function revertModel(&$model)
    {
        if (!$model->getIsDestroy()) {
            $model->destroy();
        }

        $this->pool[$model->coreName]->push($model);
    }
}
