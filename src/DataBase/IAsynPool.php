<?php
/**
 * IAsynPool
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\DataBase;

interface IAsynPool
{
    function getAsynName();

    function distribute($data);

    function execute($data);

    function serverInit($swooleServer, $asynManager);

    function workerInit($workerid);

    function pushToPool($client);

    function prepareOne();

    function addTokenCallback($callback);

    function getSync();
}