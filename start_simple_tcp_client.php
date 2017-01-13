<?php
/**
 * Created by PhpStorm.
 * User: niulingyun
 * Date: 17-1-12
 * Time: 上午9:41
 */

$GLOBALS['package_length_type'] = 'N';
$GLOBALS['package_length_type_len'] = 4;
$GLOBALS['package_length_offset'] = 0;

function encode($buffer)
{
    $total_length = $GLOBALS['package_length_type_len'] + strlen($buffer) - $GLOBALS['package_length_offset'];
    return pack($GLOBALS['package_length_type'], $total_length) . $buffer;
}

function json_pack($controller_name, $method_name, $data)
{
    $pdata['controller_name'] = $controller_name;
    $pdata['method_name'] = $method_name;
    $pdata['data'] = $data;
    return json_encode($pdata, JSON_UNESCAPED_UNICODE);
}

$i = 0;

$start = time();

while ($i < 1) {
    // 建立客户端的socet连接
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    //连接服务器端socket
    $connection = socket_connect($socket, '127.0.0.1', 9090);
    $data = encode(json_pack('User', 'Info', ['userIds' => ['07f5c5573c336f7437ac9f41', '0028195812a62c1429d288d5']]));

    if (!socket_write($socket, $data . "\n")) {
        echo "Write failed\n";
    }
    while ($buffer = socket_read($socket, 65535)) {
        $ret = substr($buffer, $GLOBALS['package_length_type_len']) . "\n";
        break;
    }

    socket_close($socket);

    if ($i % 1000 == 0) {
        echo $ret . "\n";
    }

    $i++;
}


echo time() - $start;
