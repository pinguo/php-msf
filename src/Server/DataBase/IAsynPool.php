<?php
/**
 * IAsynPool
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\DataBase;

interface IAsynPool
{
    function getAsynName();

    function distribute($data);

    function execute($data);

    function server_init($swoole_server, $asyn_manager);

    function worker_init($workerid);

    function pushToPool($client);

    function prepareOne();

    function addTokenCallback($callback);

    function getSync();
}