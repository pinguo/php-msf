<?php
/**
 * Queue Kafka Task
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Tasks;

use RdKafka\Conf;
use RdKafka\Consumer;
use RdKafka\Exception;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use RdKafka\TopicConf;

class KafkaTask extends Task
{
    /** @var string 当前要使用的kafka配置, [配置名] */
    protected $kafkaConfKey;
    /** @var array 全局kafka配置 */
    protected $config;
    /** @var array 当前kafka配置 */
    protected $setting;

    /** @var Conf */
    protected $kafkaConf;
    /** @var Producer */
    protected $kafkaProducer;
    /** @var Consumer */
    protected $kafkaConsumer;

    /**
     * 构造方法 检测配置
     * KafkaTask constructor.
     * @param string $kafkaConfKey
     * @throws Exception
     */
    public function __construct($kafkaConfKey = '')
    {
        if ($kafkaConfKey) {
            $this->kafkaConfKey = $kafkaConfKey;
        } elseif (empty($this->kafkaConfKey)) {
            throw new Exception('No $kafkaConfKey in this class or no server config in $kafkaConf');
        }

        if (!is_object($this->kafkaConf)) {
            $this->prepare($this->kafkaConfKey);
        }

        parent::__construct();
    }

    /**
     * 实例化配置
     * @param string $confKey
     * @throws Exception
     */
    public function prepare(string $confKey)
    {
        $this->config = getInstance()->config['kafka'] ?? [];
        if (!isset($this->config[$confKey])) {
            throw new Exception('No such a kafka config ' . $confKey);
        }
        $this->setting = $this->config[$confKey];
        $this->kafkaConf = new Conf();
        foreach ($this->setting as $k => $value) {
            $this->kafkaConf->set($k, $value);
        }
    }

    /**
     * 发布消息
     * @param string $message
     * @param string $topic
     * @return bool
     */
    public function produce(string $message, string $topic = 'default')
    {
        if (!is_object($this->kafkaProducer)) {
            $this->kafkaProducer = new Producer($this->kafkaConf);
            $this->kafkaProducer->addBrokers($this->setting['bootstrap.servers']);
        }
        $topic = $this->kafkaProducer->newTopic($topic);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message);
        return true;
    }

    /**
     * 消费消息
     * @param string $topic
     * @return \RdKafka\Message
     */
    public function consume(string $topic = 'default')
    {
        if (!is_object($this->kafkaConsumer)) {
            $topicConf = new TopicConf();
            $topicConf->set('auto.offset.reset', 'smallest');
            $this->kafkaConf->setDefaultTopicConf($topicConf);
            $this->kafkaConsumer = new KafkaConsumer($this->kafkaConf);
        }
        $this->kafkaConsumer->subscribe([$topic]);
        return $this->kafkaConsumer->consume(120 * 1000);
    }
}
