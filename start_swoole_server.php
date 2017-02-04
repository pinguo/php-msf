<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-17
 * Time: 下午1:56
 */

define('ROOT_PATH', __DIR__);
define('ENV', $_ENV['MSF_ENV'] ?? 'develop');

require_once __DIR__ . '/vendor/autoload.php';
$worker = new \app\AppServer();
$worker->overrideSetConfig = ['worker_num' => 4, 'task_worker_num' => 2];
Server\SwooleServer::run();