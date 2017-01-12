<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-17
 * Time: 下午1:56
 */

require_once __DIR__ . '/vendor/autoload.php';
$worker = new Server\SwooleDispatchClient();
Server\SwooleServer::run();