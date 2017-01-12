<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-17
 * Time: 下午1:56
 */
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

echo bin2hex(json_pack('TestController', 'test', 'helloworld')) . "\n";