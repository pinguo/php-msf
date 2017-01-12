<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-17
 * Time: 下午1:56
 */
require_once __DIR__ . '/vendor/autoload.php';

$ip = '192.168.21.184';
$port = 9093;

function encode($buffer)
{
    $total_length = 4 + strlen($buffer);
    return pack('N', $total_length) . $buffer;
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

});

$client->connect($ip, $port, 0.5);

function connect($cli)
{
    print_r('send');
    $message = new \app\Protobuf\Message();
    $message->setRequest(new \app\Protobuf\Request());
    $message->setResponse(new \app\Protobuf\Response());
    $loginRequest = new \app\Protobuf\Login_Request();
    $loginRequest->setUsername('test');
    $loginRequest->setPassword('123');
    $message->getRequest()->setMLoginRequest($loginRequest);
    $message->setCmdMethod(\app\Protobuf\CMD_METHOD::Login());
    $message->setCmdService(\app\Protobuf\CMD_SERVICE::Account());
    $message->setToken(time());
    $cli->send(encode($message->toStream()->getContents()));
}

function receive($cli, $data)
{
    print_r('get');
    $message = new \app\Protobuf\Message(substr($data, 4));
    print_r($message->getCmdMethod());
}