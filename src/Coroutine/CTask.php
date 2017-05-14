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

    public function __construct($taskProxyData, $id)
    {
        parent::__construct();
        $this->taskProxyData = $taskProxyData;
        $this->id            = $id;
        $args                = array_map(
            function($elem){
                return str_replace(["\n", "  "], ["", " "], var_export($elem, true));
            },
            $taskProxyData['message']['task_fuc_data']
        );
        $profileName         = $taskProxyData['message']['task_name'] . '::' . $taskProxyData['message']['task_fuc_name'] . '(' . implode(', ', $args) . ')';
        /**
         * @var Context $context
         */
        $context             = $taskProxyData['message']['task_context'];
        $context->getLog()->profileStart($profileName);

        getInstance()->coroutine->IOCallBack[$context->getLogId()][] = $this;
        $this->send(function ($serv, $taskId, $data) use ($context, $profileName) {
            if (empty(getInstance()->coroutine->taskMap[$context->getLogId()])) {
                return;
            }

            $context->getLog()->profileEnd($profileName);
            $this->result = $data;
            $this->ioBack = true;
            $this->nextRun($context->getLogId());
        });
    }

    public function send($callback)
    {
        getInstance()->server->task($this->taskProxyData, $this->id, $callback);
    }

    public function destroy()
    {
        unset($this->id);
        unset($this->taskProxyData);
    }
}