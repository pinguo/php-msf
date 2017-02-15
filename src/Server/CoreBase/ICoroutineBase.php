<?php
/**
 * 协程任务接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\CoreBase;


interface ICoroutineBase
{
    function send($callback);

    function getResult();
}