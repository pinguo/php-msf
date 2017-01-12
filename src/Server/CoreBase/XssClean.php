<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-29
 * Time: 下午12:55
 */

namespace Server\CoreBase;


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