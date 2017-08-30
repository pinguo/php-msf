<?php
/**
 * 性能压测
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\MSF\Marco;
use PG\MSF\Client\Http\Client;

/**
 * Class Bench
 * @package PG\MSF\Controllers
 */
class Bench extends Controller
{
    private $client;

    public function actionRedisPoolSet()
    {
        $this->getRedisPool('bench')->set('bench_set', 'set')->break();
        $this->outputJson('ok');
    }

    public function actionRedisPoolGet()
    {
        $val = yield $this->getRedisPool('bench')->get('bench_set');
        $this->getContext()->getOutput()->end($val);
    }

    public function actionRedisGetCallBack()
    {
        if (empty($this->client)) {
            $this->client = new \swoole_redis();
            $this->client->connect('127.0.0.1', 6379, function (\swoole_redis $client, $result) {
                if ($result === false) {
                    $this->getContext()->getOutput()->end("connect to redis server failed");
                    return;
                } else {
                    $this->getContext()->getOutput()->end("connect success");
                }
            });
        } else {
            $this->client->get('bench_set', function (\swoole_redis $client, $result) {
                $this->getContext()->getOutput()->end($result);
            });
        }
    }
}
