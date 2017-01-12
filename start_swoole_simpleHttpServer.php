<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-17
 * Time: 下午1:56
 */

$http = new swoole_http_server("127.0.0.1", 8081);
$http->set(array(
    'reactor_num' => 2, //reactor thread num
    'worker_num' => 4,    //worker process num
));
$http->on('request', function ($request, $response) {
    $response->end("helloworld");
});
$http->start();
