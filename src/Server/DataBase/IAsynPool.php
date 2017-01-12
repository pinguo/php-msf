<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-25
 * Time: 上午11:09
 */

namespace Server\DataBase;


interface IAsynPool
{
    function getAsynName();

    function distribute($data);

    function execute($data);

    function server_init($swoole_server, $asyn_manager);

    function getMessageType();

    function worker_init($workerid);

    function pushToPool($client);

    function prepareOne();

    function addTokenCallback($callback);
}