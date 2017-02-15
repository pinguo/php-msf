<?php
/**
 * ICoroutineBase
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\DataBase;

interface ICoroutineBase
{
    function send($callback);

    function getResult();
}