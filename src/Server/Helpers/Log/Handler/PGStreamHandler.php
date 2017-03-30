<?php

/**
 * @desc: Stores to any stream resource
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/3/8
 * @copyright All rights reserved.
 */
namespace PG\MSF\Server\Helpers\Log\Handler;

use Monolog\Handler\StreamHandler;

class PGStreamHandler extends StreamHandler
{
    /**
     * 写入
     * @param array $record
     */
    protected function write(array $record)
    {
        // worker 进程使用异步 IO  ，task 进程使用同步 IO
        $server = getInstance()->server;
        if (is_object($server) && property_exists($server, 'taskworker') && $server->taskworker === false) {
            if (null === $this->url || '' === $this->url) {
                throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().');
            }

            //不能异步就同步
            if (!swoole_async_writefile($this->url, (string)$record['formatted'], null, FILE_APPEND)) {
                file_put_contents($this->url, (string)$record['formatted'], FILE_APPEND);
            }
        } else {
            file_put_contents($this->url, (string)$record['formatted'], FILE_APPEND);
        }
    }

    public function close()
    {
    }
}
