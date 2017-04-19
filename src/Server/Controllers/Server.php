<?php
/**
 * Server状态
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Controllers;

class Server extends BaseController
{
    public function HttpInfo()
    {
        $data = [
            'coroutine' => [],
        ];
        $routineList = getInstance()->coroutine->taskMap;
        /**
         * @var $routine \PG\MSF\Server\Coroutine\CoroutineTask
         */
        foreach ($routineList as $routine) {
            $logId = $routine->generatorContext->getController()->PGLog->logId;
            $name = get_class($routine->getRoutine()->current()) . '#' . spl_object_hash($routine->getRoutine()->current());
            $data['coroutine'][$logId][$name]['timeout'] = $routine->getRoutine()->current()->timeout;
            $data['coroutine'][$logId][$name]['run_time'] = strval(number_format(1000 * (microtime(true) - $routine->getRoutine()->current()->requestTime),
                4, '.', ''));
            $data['coroutine'][$logId][$name]['request_time'] = strval(number_format(1000 * (microtime(true) - $routine->generatorContext->getController()->requestStartTime),
                4, '.', ''));
            $data['coroutine'][$logId][$name]['profile'] = $routine->generatorContext->getController()->PGLog->getAllProfileInfo();
        }
        $data['coroutine']['total'] = count($data['coroutine']);
        $data['memory']['peak'] = strval(number_format(memory_get_peak_usage() / 1024 / 1024, 3, '.', '')) . 'M';
        $data['memory']['usage'] = strval(number_format(memory_get_usage() / 1024 / 1024, 3, '.', '')) . 'M';
        $this->outputJson($data, 'success');
    }

    /**
     * Http 框架Hello World
     */
    public function HttpHelloWorld()
    {
        $this->outputJson('Hello World');
    }

    /**
     * Http 服务状态探测
     */
    public function HttpStatus()
    {
        $client = yield $this->client->coroutineGetHttpClient('http://localhost');
        $data = yield $client->coroutineGet('/');
        $this->output->end($data);
    }

    /**
     * Tcp 服务状态探测
     */
    public function TcpStatus()
    {
        $this->outputJson('ok');
    }

    public function HttpTcp()
    {
        $tcpClient = yield $this->tcpClient->coroutineGetTcpClient('localhost:8000');
        $data = yield $tcpClient->coroutineSend(['path' => 'server/status', 'data' => 1234]);
        $this->outputJson($data);
    }

    public function HttpRedis()
    {
        yield $this->redisPool->incrBy('key', 1);
        $value = yield $this->redisPool->cache('key');
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
        $client = $this->client->coroutineGetHttpClient('http://phototask-feed-ms.360in.com');
        $httpClient = yield $client;
        if (!$httpClient) {
            $this->outputJson('network error', 500);
        } else {
            $prePost = $httpClient->coroutineGet(
                '/feed/inner/feedInner/hotFeed?appVersion=8.3.2&platform=ios&locale=zh-Hans&C78818A7-26DD-4795-ACB6-663496AA5A32&ip=127.0.0.1&taskId=&longitude=104.0679504901092&30.53893220660016&channel=appstore&catIds=196608&catNums=100'
            );
            $data = yield $prePost;
            $this->outputJson($data['body']);
        }
    }
}