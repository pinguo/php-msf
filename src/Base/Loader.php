<?php
/**
 * 自动加载器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\MSF\Tasks\TaskProxy;
use PG\MSF\Models\ModelFactory;

class Loader
{
    /**
     * Tasks list
     * @var array
     */
    private $_tasks = [];
    private $_modelFactory;

    public function __construct()
    {
        $this->_modelFactory = ModelFactory::getInstance();
    }

    /**
     * 获取一个model
     * @param $model
     * @param Child $parent
     * @return mixed|null
     * @throws Exception
     */
    public function model($model, Child $parent)
    {
        if (!$parent->getIsConstruct()) {
            $parentName = get_class($parent);
            throw new Exception("class:$parentName,error:loader model 方法不允许在__construct内使用！");
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
     * @throws Exception
     */
    public function task($task, Child $parent = null)
    {
        if (empty($task)) {
            return null;
        }

        $taskClass = $task;
        do {
            if (class_exists($taskClass)) {
                break;
            }

            $taskClass = "\\App\\Tasks\\$task";
            if (class_exists($taskClass)) {
                break;
            }

            $taskClass = "\\PG\\MSF\\Tasks\\$task";
            if (class_exists($taskClass)) {
                break;
            }

            throw new Exception("class {$taskClass} not exists");
        } while (0);

        if (!getInstance()->server->taskworker) {
            if ($parent != null && method_exists($parent, 'getObjectPool')) {
                $taskProxy = $parent->getObjectPool()->get(TaskProxy::class);
            } else {
                $taskProxy = new TaskProxy();
                if ($parent != null) {
                    $taskProxy->setContext($parent->getContext());
                }
            }

            if ($parent != null) {
                $parent->addChild($taskProxy);
            }

            $taskProxy->coreName = $taskClass;

            return $taskProxy;
        }

        if (key_exists($task, $this->_tasks)) {
            $taskInstance = $this->_tasks[$task];
            $taskInstance->reUse();

            return $taskInstance;
        } else {
            $taskInstance        = new $taskClass;
            $this->_tasks[$task] = $taskInstance;

            return $taskInstance;
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
