<?php
/**
 * Created by PhpStorm.
 * User: niulingyun
 * Date: 17-1-11
 * Time: 下午5:04
 */

namespace Server\Helpers\Log;

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
            //过滤器
            $stream = new FilterHandler($stream, $handler['levelList']);
            $this->pushHandler($stream);
        }
    }
}
