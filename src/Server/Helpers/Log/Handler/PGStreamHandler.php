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
        if (!get_instance()->server->taskworker) {
            if (null === $this->url || '' === $this->url) {
                throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().');
            }

            if (!swoole_async_write($this->url, (string) $record['formatted'], -1)) {
                throw new \UnexpectedValueException(sprintf('Writing to stream or file "%s" could not be finished: ',
                    $this->url));
            }
        } else {
            parent::write($record);
        }
    }

    public function close()
    {
        if (get_instance()->server->taskworker) {
            parent::close();
        }
    }
}
