<?php
/**
 * Queue amqpMQ Task
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 * Date: 26/10/2017
 */

namespace PG\MSF\Tasks;

class AMQPTask extends Task
{
    /**
     * @var array 当前要使用的amqp配置, [配置名, exchange名, routing_key名]
     */
    protected $amqpConf = [];
    /**
     * @var array 全局amqpMQ配置
     */
    protected $config;

    /** @var \AMQPConnection */
    protected $amqpConnection;

    /** @var \AMQPChannel */
    protected $amqpChannel;

    /** @var \AMQPExchange */
    protected $amqpExchange;

    /** @var \AMQPQueue */
    protected $amqpQueue;

    /**
     * 构造方法 检测连接
     * AMQPTask constructor.
     * @param array ...$amqpConf
     * @throws Exception
     */
    public function __construct(...$amqpConf)
    {
        if ($amqpConf) {
            $this->amqpConf = $amqpConf;
        } elseif (empty($this->amqpConf)) {
            throw new Exception('No $amqpConf in this class or no server config in $amqpConf');
        }

        if (!is_object($this->amqpConnection) || !$this->amqpConnection->isConnected()) {
            $this->prepare($this->amqpConf[0], $this->amqpConf[1] ?? 'default', $this->amqpConf[2] ?? 'default');
        }

        parent::__construct();
    }

    /**
     * 长连接
     * @param string $confKey
     * @param string $exchange
     * @param string $routing_key
     * @throws \AMQPException
     */
    public function prepare(string $confKey, string $exchange, string $routing_key)
    {
        $this->profileName = 'amqp.' . $exchange . '.';
        $this->config = getInstance()->config['amqp'] ?? [];
        if (!isset($this->config[$confKey])) {
            throw new \AMQPException('No such a amqpMQ config ' . $confKey);
        }
        $conf = $this->config[$confKey];
        $this->amqpConnection = new \AMQPConnection([
            'host'            => $conf['host']              ??  'localhost',
            'port'            => $conf['port']              ??  '5672',
            'vhost'           => $conf['vhost']             ??  '/',
            'login'           => $conf['login']             ??  'guest',
            'password'        => $conf['password']          ??  'guest',
            'read_timeout'    => $conf['read_timeout']      ??  0,
            'write_timeout'   => $conf['write_timeout']     ??  0,
            'connect_timeout' => $conf['connect_timeout']   ??  0,
            'channel_max'     => $conf['channel_max']       ??  256,
            'frame_max'       => $conf['frame_max']         ??  131072,
            'heartbeat'       => $conf['heartbeat']         ??  0,
            'cacert'          => $conf['cacert']            ??  null,
            'key'             => $conf['key']               ??  null,
            'cert'            => $conf['cert']              ??  null,
            'verify'          => $conf['verify']            ??  1
        ]);

        //Establish connection AMQP
        $this->amqpConnection->pconnect();

        //Create and declare channel
        $this->amqpChannel = new \AMQPChannel($this->amqpConnection);

        //AMQP Exchange is the publishing mechanism
        $this->amqpExchange = new \AMQPExchange($this->amqpChannel);
        //Declare Exchange
//        $this->amqpExchange->setType(AMQP_EX_TYPE_DIRECT);
//        $this->amqpExchange->setName($exchange);
//        $this->amqpExchange->declareExchange();

        //Declare Queue
        $this->amqpQueue = new \AMQPQueue($this->amqpChannel);
        $this->amqpQueue->setName($routing_key);
        $this->amqpQueue->setFlags(AMQP_NOPARAM);
        $this->amqpQueue->declareQueue();
    }

    /**
     * 发布消息
     * @param string $message
     * @param string $routing_key
     * @return bool
     */
    public function publish(string $message, string $routing_key = 'default')
    {
        return $this->amqpExchange->publish($message, $routing_key);
    }

    /**
     * 读取消息
     * @param int $autoAck 自否在MQ中清除
     * @return \AMQPEnvelope|bool
     */
    public function get($autoAck = 1)
    {
        if ($autoAck) {
            return $this->amqpQueue->get(AMQP_AUTOACK);
        } else {
            return $this->amqpQueue->get();
        }
    }

    /**
     * 消费消息
     * @param callable $callback 回调函数
     * @param int $autoAck 自否在MQ中清除
     */
    public function consume(callable $callback, $autoAck = 1)
    {
        if ($autoAck) {
            $this->amqpQueue->consume($callback, AMQP_AUTOACK);
        } else {
            $this->amqpQueue->consume($callback);
        }
    }
}
