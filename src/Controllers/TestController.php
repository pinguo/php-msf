<?php
/**
 * TestController
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\InnerServ\Geo\PGGeo;
use PG\InnerServ\InnerServ;
use PG\InnerServ\Sso\PGUserInfo;
use PG\MSF\Models\TestModel;
use PG\MSF\Client\Http\Client;
use PG\MSF\Client\Tcp\Client as TClient;

class TestController extends Controller
{
    /**
     * @var TestModel
     */
    public $testModel;

    /**
     * @return boolean
     */
    public function isIsDestroy()
    {
        return $this->isDestroy;
    }

    public function HttpTcp()
    {
        $client    = $this->getContext()->getObjectPool()->get(TClient::class);
        $tcpClient = yield $client->coroutineGetTcpClient('localhost:8000');
        $data      = yield $tcpClient->coroutineSend(['path' => 'server/status', 'data' => 1234]);
        $this->outputJson($data);
    }

    public function HttpRedis()
    {
        yield $this->getRedisPool('tw')->incrBy('key', 1);
        $value = yield $this->getRedisPool('tw')->cache('key');
        $this->outputJson($value);
    }

    public function httpRedisProxy()
    {
        $redis = $this->getRedisProxy('redisProxy');
        $data = [];
        yield $redis->incrBy('aaa', 1);
        $data[] = yield $redis->get('aaa');
        yield $redis->incrBy('bbb', 1);
        $data[] = yield $redis->get('bbb');
        yield $redis->incrBy('ccc', 1);
        $data[] = yield $redis->get('ccc');
        yield $redis->incrBy('ddd', 1);
        $data[] = yield $redis->get('ddd');


        $arr = [
            'eee' => 'EEE',
            'fff' => 'FFF',
            'ggg' => 'GGG',
            'hhh' => 'HHH',
            'iii' => 'III',
            'jjj' => 'JJJ',
            'kkk' => 'KKK',
            'lll' => 'LLL',
            'mmm' => 'MMM',
            'nnn' => 'NNN'
        ];

        yield $redis->mset($arr);
        $data[] = yield $redis->mget(array_keys($arr));

        $this->outputJson($data);
    }

    public function httpRedisProxyMS()
    {
        $data = [];
        $redis = $this->getRedisProxy('redisProxy1');
        yield $redis->incrBy('rw', 1);
        $data[] = yield $redis->get('rw');

        $this->outputJson($data);
    }

    public function HttpTestCoroutine()
    {
        $client        = $this->getContext()->getObjectPool()->get(Client::class);
        $httpClient    = $client->coroutineGetHttpClient('http://phototask-feed-ms.360in.com');
        $httpClientDns = yield $httpClient;
        if (!$httpClientDns) {
            $this->outputJson('network error', 500);
        } else {
            $prePost = $httpClientDns->coroutineGet(
                '/feed/inner/feedInner/hotFeed?appVersion=8.3.2&platform=ios&locale=zh-Hans&C78818A7-26DD-4795-ACB6-663496AA5A32&ip=127.0.0.1&taskId=&longitude=104.0679504901092&30.53893220660016&channel=appstore&catIds=196608&catNums=100'
            );
            $data = yield $prePost;
            $this->outputJson($data['body']);
        }
    }

    public function httpUser()
    {
        $obj = InnerServ::get(PGUserInfo::class, $this->getContext());
        $obj->initialization();
        $data = yield $obj->getUserInfo('0108d9571b25aec97cbf5b57', '', true, true);
        $this->outputJson($data);
    }
}
