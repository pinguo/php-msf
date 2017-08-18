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

class a extends \Exception{

}
class TestController extends Controller
{
    /**
     * @var TestModel
     */
    public $testModel;

    /**
     * @var array
     */
    private $dns;

    /**
     * @return boolean
     */
    public function isIsDestroy()
    {
        return $this->__isDestroy;
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
        $redis = $this->getRedisProxy('redis_proxy');
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
        $redis = $this->getRedisProxy('redis_proxy1');
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

    public function httpWeatherApi()
    {
        $data = yield $this->getContext()->getObjectPool()
            ->get(\PG\MSF\Client\Http\Client::class)
            ->coroutineGet('http://www.weather.com.cn/data/sk/101110101.html');
        $this->getContext()->getOutput()->end($data);
    }

    public function httpWeatherApiCallBack()
    {
        $ip = getInstance()->sysCache->get('www.weather.com.cn');
        if ($ip) {
            $cli = new \swoole_http_client($ip, 80);
            $cli->setHeaders([
                'Host' => 'www.weather.com.cn',
                "User-Agent" => 'Chrome/49.0.2587.3',
            ]);

            $cli->get('/data/sk/101110101.html', function ($cli) {
                $this->getContext()->getOutput()->end($cli->body);
            });
        } else {
            swoole_async_dns_lookup("www.weather.com.cn", function ($host, $ip) {
                getInstance()->sysCache->set('www.weather.com.cn', $ip);
                $cli = new \swoole_http_client($ip, 80);
                $cli->setHeaders([
                    'Host' => $host,
                    "User-Agent" => 'Chrome/49.0.2587.3',
                ]);

                $cli->get('/data/sk/101110101.html', function ($cli) {
                    $this->getContext()->getOutput()->end($cli->body);
                });
            });
        }
    }
}
