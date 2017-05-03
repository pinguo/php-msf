<?php
/**
 * 协程任务接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;


interface IBase
{
    function isTimeout();
    
    function send($callback);

    function getResult();
}