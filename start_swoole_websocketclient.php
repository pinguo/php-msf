<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-17
 * Time: 下午1:56
 */
$worker_num = 100;
$total_num = 100000;
$ips = ['127.0.0.1'];
$GLOBALS['total_num'] = $total_num;
$GLOBALS['worker_num'] = $worker_num;
$GLOBALS['count'] = 0;
$GLOBALS['count_page'] = 0;
$GLOBALS['test_count'] = 0;
for ($i = 0; $i < $total_num; $i++) {
    $GLOBALS['test'][$i] = $i;
}
function json_pack($controller_name, $method_name, $data)
{
    $pdata['controller_name'] = $controller_name;
    $pdata['method_name'] = $method_name;
    $pdata['data'] = $data;
    return json_encode($pdata, JSON_UNESCAPED_UNICODE);
}

for ($i = 1; $i <= $worker_num; $i++) {
    $client = new swoole_http_client($ips[array_rand($ips)], 8081);
    $client->count = 0;
    $client->i = $i;
    $client->total_num = $total_num / $worker_num;

    $client->on("message", 'message');
    $client->upgrade('/', 'upgrade');
    $client->on("error", function ($cli) {
        exit("error\n");
    });

    $client->on("close", function ($cli) {

    });
}
function upgrade($cli)
{
    $cli->push(json_pack('TestController', 'bind_uid', $cli->i));
    swoole_timer_after(1000, function () use ($cli) {
        $GLOBALS['start_time'] = getMillisecond();
        for ($i = 0; $i < $cli->total_num; $i++) {
            $cli->push(json_pack('TestController', 'efficiency_test2', $GLOBALS['test_count']));
            $GLOBALS['test_count']++;
        }
    });
}

function message($cli, $frame)
{
    $data = $frame->data;
    $GLOBALS['count']++;
    unset($GLOBALS['test'][$data]);
    if ($GLOBALS['count'] >= ($GLOBALS['count_page'] + 1) * $GLOBALS['total_num'] / 5) {
        print_r("Left->" . count($GLOBALS['test']) . "\tGet->" . $GLOBALS['count'] . "\n");
        $GLOBALS['count_page']++;
    }

    if (count($GLOBALS['test']) == 0) {
        $total_time = getMillisecond() - $GLOBALS['start_time'];
        $qps = $GLOBALS['total_num'] / $total_time * 1000;
        print_r("qps:$qps\n");
        exit(0);
    }
}

function getMillisecond()
{
    list($s1, $s2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
}