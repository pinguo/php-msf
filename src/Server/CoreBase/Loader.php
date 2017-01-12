<?php
/**
 * Loader 加载器
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午12:21
 */

namespace Server\CoreBase;

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
     */
    public function model($model, Child $parent)
    {
        if (empty($model)) {
            return null;
        }
        if ($parent->hasChild($model)) {
            return $parent->getChild($model);
        }
        $model_instance = $this->_model_factory->getModel($model);
        $parent->addChild($model_instance);
        return $model_instance;
    }

    /**
     * 获取一个task
     * @param $task
     * @return mixed|null|TaskProxy
     * @throws SwooleException
     */
    public function task($task)
    {
        if (empty($task)) {
            return null;
        }
        $task = str_replace('/', '\\', $task);
        $task_class = "\\app\\Tasks\\" . $task;
        if (!class_exists($task_class)) {
            $task_class = "\\Server\\Tasks\\" . $task;
            if (!class_exists($task_class)) {
                throw new SwooleException("class task_class not exists");
            }
        }
        if (!get_instance()->server->taskworker) {//工作进程返回taskproxy
            $this->_task_proxy->core_name = $task;
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