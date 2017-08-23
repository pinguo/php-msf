<?php
/**
 * IAsynPool
 *
 * @author tmtbe
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pools;

interface IAsynPool
{
    function getAsynName();

    function distribute($data);

    function execute($data);

    function serverInit($swooleServer, $asynManager);

    function workerInit($workerId);

    function pushToPool($client);

    function prepareOne();

    function addTokenCallback($callback);

    function getSync();
}
