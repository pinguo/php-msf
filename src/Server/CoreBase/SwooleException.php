<?php
/**
 * SwooleException
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

use PG\MSF\Server\Controllers\BaseController;

class SwooleException extends \Exception
{
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        print_r($message . "\n");
    }

    /**
     * 设置追加信息
     * @param $others
     * @param  $controller
     */
    public function setShowOther($others, $controller = null)
    {
        if (!empty($others)) {
            print_r($others . "\n");
        } else {
            print_r($this->getMessage() . "\n");
            print_r($this->getTraceAsString() . "\n");
        }
        print_r("\n");
        if (!empty($controller) && $controller instanceof BaseController) {
            $controller->PGLog->warning($others);
        }
    }
}