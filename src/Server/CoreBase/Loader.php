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
    private $_task_proxy;
    private $_model_factory;

    public function __construct()
    {
        $this->_task_proxy = new TaskProxy();
        $this->_model_factory = ModelFactory::getInstance();
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
        if ($model == $parent->core_name) {
            return $parent;
        }
        $root = $parent;
        while (isset($root)) {
            if ($root->hasChild($model)) {
                return $root->getChild($model);
            }
            $root = $root->parent??null;
        }

        $model_instance = $this->_model_factory->getModel($model, $parent);
        $parent->addChild($model_instance);
        $model_instance->initialization($parent->getContext());
        return $model_instance;
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
        if (!get_instance()->server->taskworker) {//工作进程返回taskproxy
            $this->_task_proxy->core_name = $task;
            if ($parent != null) {
                $this->_task_proxy->setContext($parent->getContext());
            }
            return $this->_task_proxy;
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
        $template = get_instance()->templateEngine->make($template);
        return $template;
    }
}