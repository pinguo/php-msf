<?php
/**
 * XssClean
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;

use voku\helper\AntiXSS;

class XssClean
{
    protected static $xss_clean;

    public static function getXssClean()
    {
        if (self::$xss_clean == null) {
            self::$xss_clean = new AntiXSS();
        }
        return self::$xss_clean;
    }
}