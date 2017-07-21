<?php
/**
 * 自动加载器
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use Exception;
use PG\MSF\Tasks\TaskProxy;
use PG\MSF\Tasks\Task;
use PG\MSF\Models\Factory as ModelFactory;
use PG\MSF\Models\Model;

class Loader
{
    /**
     * Tasks list（Task进程中单例模式）
     * @var array
     */
    private $_tasks = [];

    /**
     * 获取Model
     *
     * @param $model
     * @param Child $parent
     * @return Model|null
     * @throws Exception
     */
    public function model($model, Child $parent)
    {
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

        $modelInstance = ModelFactory::getInstance()->getModel($model);
        $parent->addChild($modelInstance);
        $modelInstance->initialization($parent->getContext());
        return $modelInstance;
    }

    /**
     * 获取Task
     * @param $task
     * @param Child $parent
     * @return null|Task||TaskProxy
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

        if (!empty(getInstance()->server) && property_exists(getInstance()->server, 'taskworker') && !getInstance()->server->taskworker) { // worker进程
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
            $taskInstance->isUse();
        } else {
            $taskInstance        = new $taskClass;
            $this->_tasks[$task] = $taskInstance;
        }

        if ($parent != null) {
            $parent->addChild($taskInstance);
            $taskInstance->setContext($parent->getContext());
        }
        $taskInstance->coreName = $taskClass;

        return $taskInstance;
    }

    /**
     * view 返回一个模板
     *
     * @param $template
     * @return \League\Plates\Template\Template
     */
    public function view($template)
    {
        $template = getInstance()->templateEngine->make($template);
        return $template;
    }
}
