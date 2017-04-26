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
     * @throws SwooleException
     */
    public function getModel($model)
    {
        $model = str_replace('/', '\\', $model);

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

        $className = "\\App\\Models\\$model";
        if (class_exists($className)) {
            $modelInstance = new $className;
            $modelInstance->coreName = $model;
            $modelInstance->afterConstruct();
        } else {
            $className = "\\PG\\MSF\\Server\\Models\\$model";
            if (class_exists($className)) {
                $modelInstance = new $className;
                $modelInstance->coreName = $model;
                $modelInstance->afterConstruct();
            } else {
                throw new SwooleException("class $model is not exist");
            }
        }

        $modelInstance->genTime = time();
        $modelInstance->useCount   = 1;

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

        //判断是否还返还对象：使用时间超过2小时或者使用次数大于10000则不返还，直接销毁
        if (($model->genTime + 7200) < time() || $model->useCount > 10000) {
            unset($model);
        } else {
            $this->pool[$model->coreName]->push($model);
        }
    }
}