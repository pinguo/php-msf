<?php
/**
 * 自动加载器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

class Loader
{
    /**
     * Tasks list
     * @var array
     */
    private $_tasks = [];
    private $_taskProxy;
    private $_modelFactory;

    public function __construct()
    {
        $this->_taskProxy = new TaskProxy();
        $this->_modelFactory = ModelFactory::getInstance();
    }

    /**
     * 获取一个model
     * @param $model
     * @param Child $parent
     * @return mixed|null
     * @throws SwooleException
     */
    public function model($model, Child $parent)
    {
        if (!$parent->isConstruct) {
            $parentName = get_class($parent);
            throw new SwooleException("class:$parentName,error:loader model 方法不允许在__construct内使用！");
        }
        if (empty($model)) {
            return null;
        }
        if ($model == $parent->coreName) {
            return $parent;
        }
        $root = $parent;
        while (isset($root)) {
            if ($root->hasChild($model)) {
                return $root->getChild($model);
            }
            $root = $root->parent??null;
        }

        $modelInstance = $this->_modelFactory->getModel($model, $parent);
        $parent->addChild($modelInstance);
        $modelInstance->initialization($parent->getContext());
        return $modelInstance;
    }

    /**
     * 获取一个task
     * @param $task
     * @param Child $parent
     * @return mixed|null|TaskProxy
     * @throws SwooleException
     */
    public function task($task, Child $parent = null)
    {
        if (empty($task)) {
            return null;
        }
        $task = str_replace('/', '\\', $task);
        $task_class = "\\App\\Tasks\\" . $task;
        if (!class_exists($task_class)) {
            $task_class = "\\PG\\MSF\\Server\\Tasks\\" . $task;
            if (!class_exists($task_class)) {
                throw new SwooleException("class task_class not exists");
            }
        }
        if (!getInstance()->server->taskworker) {//工作进程返回taskproxy
            $this->_taskProxy->coreName = $task;
            if ($parent != null) {
                $this->_taskProxy->setContext($parent->getContext());
            }
            return $this->_taskProxy;
        }
        if (key_exists($task, $this->_tasks)) {
            $task_instance = $this->_tasks[$task];
            $task_instance->reUse();
            return $task_instance;
        } else {
            $task_instance = new $task_class;
            $this->_tasks[$task] = $task_instance;
            return $task_instance;
        }
    }

    /**
     * view 返回一个模板
     * @param $template
     * @return \League\Plates\Template\Template
     */
    public function view($template)
    {
        $template = getInstance()->templateEngine->make($template);
        return $template;
    }
}