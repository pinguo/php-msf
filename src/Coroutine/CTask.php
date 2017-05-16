<?php
/**
 * CTask
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Helpers\Context;

class CTask extends Base
{
    public $id;
    public $taskProxyData;
    /**
     * @var Context
     */
    public $context;

    public function __construct($taskProxyData, $id)
    {
        parent::__construct();
        $this->taskProxyData = $taskProxyData;
        $this->id            = $id;
        $args                = array_map(
            function ($elem) {
                return str_replace(["\n", "  "], ["", " "], var_export($elem, true));
            },
            $taskProxyData['message']['task_fuc_data']
        );
        $profileName         = $taskProxyData['message']['task_name'] . '::' . $taskProxyData['message']['task_fuc_name'] . '(' . implode(', ', $args) . ')';
        /**
         * @var Context $context
         */
        $this->context       = $taskProxyData['message']['task_context'];
        $logId               = $this->context->getLogId();

        $this->context->getLog()->profileStart($profileName);
        getInstance()->coroutine->IOCallBack[$logId][] = $this;
        $this->send(function ($serv, $taskId, $data) use ($profileName, $logId) {
            if (empty(getInstance()->coroutine->taskMap[$logId])) {
                return;
            }

            $this->context->getLog()->profileEnd($profileName);
            $this->result = $data;
            $this->ioBack = true;
            $this->nextRun($logId);
        });
    }

    public function send($callback)
    {
        getInstance()->server->task($this->taskProxyData, $this->id, $callback);
    }

    public function destroy()
    {
        unset($this->context);
        unset($this->id);
        unset($this->taskProxyData);
    }
}
