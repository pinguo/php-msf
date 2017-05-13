<?php
/**
 * Console Controller基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Console;

use PG\MSF\Controllers\Controller as BController;
use PG\Log\PGLog;
use PG\MSF\Marco;
use PG\MSF\Helpers\Context;

class Controller extends BController
{
    public function initialization($controllerName, $methodName)
    {
    }

    public function destroy()
    {
        $this->getContext()->getLog()->pushLog('params', $this->getContext()->getInput()->getAllPostGet());
        $this->getContext()->getLog()->pushLog('status', '200');
        $this->getContext()->getLog()->appendNoticeLog();
        parent::destroy();
        clearTimes();
        exit();
    }

}
