<?php
/**
 * XssClean
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Base;

use voku\helper\AntiXSS;

class XssClean
{
    protected static $xssClean;

    public static function getXssClean()
    {
        if (self::$xssClean == null) {
            self::$xssClean = new AntiXSS();
        }
        return self::$xssClean;
    }
}