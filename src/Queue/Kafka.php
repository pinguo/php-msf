<?php
/**
 * Queue Kafka
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Queue;

use PG\MSF\Base\Core;
use PG\MSF\Tasks\KafkaTask;

class Kafka extends Core implements IQueue
{
    /** @var KafkaTask */
    public $kafka;

    public function __construct(string $configKey)
    {
        $this->kafka = $this->getObject(KafkaTask::class, [$configKey]);
    }

    /**
     * 入队
     * @param string $data
     * @param string $queue
     * @return \Generator
     */
    public function set(string $data, string $queue = 'default')
    {
        return yield $this->kafka->produce($data);
    }

    /**
     * 出队
     * @param string $queue
     * @return bool|null
     * @throws \RdKafka\Exception
     */
    public function get(string $queue = 'default')
    {
        $message = yield $this->kafka->consume();
        if (!is_object($message)) {
            return false;
        }

        if ($message->err === RD_KAFKA_RESP_ERR_NO_ERROR) {//正常
            return $message->payload;
        } elseif ($message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF) {//暂时没有消息
            return null;
        } elseif ($message->err === RD_KAFKA_RESP_ERR__TIMED_OUT) {//超时
            return false;
        } else {
            throw new \RdKafka\Exception('RD_KAFKA_RESP_ERR', $message->err);//其他异常
        }
    }
}
