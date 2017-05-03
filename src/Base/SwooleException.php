<?php
/**
 * SwooleException
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use PG\Exception\BusinessException;
use PG\MSF\Controllers\BaseController;

class SwooleException extends \Exception
{
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 设置追加信息
     * @param $others
     * @param BaseController $controller
     */
    public function setShowOther($others, $controller = null)
    {
        //  BusinessException 不打印在终端.
        if ($this->getPrevious() instanceof BusinessException) {
            return;
        }
        if (!empty($others)) {
            print_r($others . "\n");
        } else {
            print_r($this->getMessage() . "\n");
            print_r($this->getTraceAsString() . "\n");
        }
        print_r("\n");
    }
}