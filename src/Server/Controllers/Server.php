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
        $routineList = getInstance()->coroutine->routineList;
        /**
         * @var $routine \PG\MSF\Server\CoreBase\CoroutineTask
         */
        foreach ($routineList as $routine) {
            $logId = $routine->generatorContext->getController()->PGLog->logId;
            $name  = get_class($routine->getRoutine()->current()) . '#' . spl_object_hash($routine->getRoutine()->current());
            $data['coroutine'][$logId][$name]['get_result_count'] = $routine->getRoutine()->current()->getCount;
            $data['coroutine'][$logId][$name]['timeout']          = $routine->getRoutine()->current()->timeout;
            $data['coroutine'][$logId][$name]['run_time']         = strval(number_format(1000*(microtime(true) - $routine->getRoutine()->current()->requestTime), 4, '.', ''));
            $data['coroutine'][$logId][$name]['request_time']     = strval(number_format(1000*(microtime(true) - $routine->generatorContext->getController()->requestStartTime), 4, '.', ''));
            $data['coroutine'][$logId][$name]['profile']          = $routine->generatorContext->getController()->PGLog->getAllProfileInfo();
        }
        $data['coroutine']['total'] = count($data['coroutine']);
        $data['memory']['peak']     = strval(number_format(memory_get_peak_usage()/1024/1024, 3, '.', '')) . 'M';
        $data['memory']['usage']    = strval(number_format(memory_get_usage()/1024/1024, 3, '.', '')) . 'M';
        $this->outputJson($data, 'success');
    }

    /**
     * Http 服务状态探测
     */
    public function HttpStatus()
    {
        $client = yield $this->client->coroutineGetHttpClient('http://localhost');
        $data   = yield $client->coroutineGet('/');
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
        $data      = yield $tcpClient->coroutineSend(['path' => 'server/status', 'data' => 1234]);
        $this->outputJson($data);
    }

    public function HttpRedis()
    {
        yield $this->redisPool->incrBy('key', 1);
        $value = yield $this->redisPool->cache('key');
        $this->outputJson($value);
    }
}