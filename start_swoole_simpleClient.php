<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-17
 * Time: 下午1:56
 */

$ips = ['192.168.21.10', '192.168.21.11'];
function encode($buffer)
{
    $total_length = 4 + strlen($buffer);
    return pack('N', $total_length) . $buffer;
}

function json_pack($controller_name, $method_name, $data)
{
    $pdata['controller_name'] = $controller_name;
    $pdata['method_name'] = $method_name;
    $pdata['data'] = $data;
    return json_encode($pdata, JSON_UNESCAPED_UNICODE);
}

$client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC); //异步非阻塞
$client->set(array(
    'open_length_check' => 1,
    'package_length_type' => 'N',
    'package_length_offset' => 0,       //第N个字节是包长度的值
    'package_body_offset' => 0,       //第几个字节开始计算长度
    'package_max_length' => 2000000,  //协议最大长度
));
$client->on("connect", 'connect');

$client->on("receive", 'receive');

$client->on("error", function ($cli) {
    exit("error\n");
});

$client->on("close", function ($cli) {
    print_r("close\n");
});

$client->connect($ips[array_rand($ips)], 9093, 0.5);

function connect($cli)
{
    $cli->send(encode(json_pack('TestController', 'bind_uid', 1001)));
}

function receive($cli, $data)
{
    print_r($data);
}
