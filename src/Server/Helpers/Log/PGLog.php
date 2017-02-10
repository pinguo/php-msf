<?php
/**
 * Created by PhpStorm.
 * User: niulingyun
 * Date: 17-1-11
 * Time: 下午5:04
 */

namespace Server\Helpers\Log;

use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FilterHandler;

class PGLog extends Logger
{
    public function __construct(string $name, array $handlers = [], array $processors = [], DateTimeZone $timezone = null)
    {
        parent::__construct($name, $handlers, $processors, $timezone);
        $server = get_instance();
        $cofig = $server->config;
        foreach ($cofig['server.log.handlers'] as $handler) {
            $stream = new StreamHandler($handler['stream']);

            //格式
            if (isset($handler['format']) && isset($handler['dateFormat'])) {
                $format = new LineFormatter($handler['format'], $handler['dateFormat']);
                $stream->setFormatter($format);
            }

            //buffer
            if ($handler['buffer'] > 0) {
                $stream = new BufferHandler($stream, $handler['buffer'], Logger::DEBUG, true, true);
            }

            //过滤器
            $stream = new FilterHandler($stream, $handler['levelList']);

            $this->pushHandler($stream);
        }
    }

    /**
     * 日志中增加logId字段
     * @param string $logId
     */
    public function pushLogId(string $logId)
    {
        $this->pushProcessor(function ($record) use($logId) {
            $record['logId'] = $logId;
            return $record;
        });
    }

    public function pushMemoryUsage()
    {

    }

    public function pushTimeUsage()
    {

    }

    /**
     * 日志中增加uri
     * @param string $uri
     */
    public function pushUri(string $uri)
    {
        $this->pushProcessor(function ($record) use($uri) {
            $record['context'][] = $uri;
            return $record;
        });
    }
}
