<?php
/**
 * Queue RabbitMQ
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Queue;

use PG\MSF\Base\Core;
use PG\MSF\Tasks\AMQPTask;

class RabbitMQ extends Core implements IQueue
{
    /** @var mixed|AMQPTask|\stdClass */
    public $rabbit;

    public function __construct(string $configKey, $routing_key = 'default')
    {
        $this->rabbit = $this->getObject(AMQPTask::class, [$configKey, $routing_key]);
    }

    /**
     * 入队
     * @param string $data
     * @param string $queue
     * @return bool
     */
    public function set(string $data, string $queue = 'default')
    {
        return $this->rabbit->publish($data, $queue);
    }

    /**
     * 出队
     * @param string $queue
     * @param bool $isAck
     * @return \AMQPEnvelope|string
     */
    public function get(string $queue = 'default', $isAck = true)
    {
        /** @var \AMQPEnvelope $AMQPEnvelope */
        $AMQPEnvelope = yield $this->rabbit->get($isAck);
        if (!is_object($AMQPEnvelope)) {
            return $AMQPEnvelope;
        }
        return $AMQPEnvelope->getBody();
    }
}
