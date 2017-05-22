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

    public function initialization($taskProxyData, $id)
    {
        parent::init();
        $this->taskProxyData = $taskProxyData;
        $this->id            = $id;
        $args                = array_map(
            function ($elem) {
                if (is_string($elem) && strlen($elem) > 4096) {
                    return 'string[too big, not display]';
                } else {
                    return str_replace(["\n", "  "], ["", " "], var_export($elem, true));
                }
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
        
        return $this;
    }

    public function send($callback)
    {
        getInstance()->server->task($this->taskProxyData, $this->id, $callback);
    }

    public function destroy()
    {
        parent::destroy();
    }
}
