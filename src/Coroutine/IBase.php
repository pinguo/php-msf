<?php
/**
 * 协程接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

/**
 * Interface IBase
 * @package PG\MSF\Coroutine
 */
interface IBase
{
    function isTimeout();
    
    function send($callback);

    function getResult();
}
