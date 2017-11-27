<?php
/**
 * Redis协程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\Pools\RedisAsynPool;
use PG\MSF\Macro;

/**
 * Class Redis
 * @package PG\MSF\Coroutine
 */
class Redis extends Base
{
    /**
     * @var string Redis操作指令
     */
    public $name;

    /**
     * @var array Redis操作指令的参数
     */
    public $arguments;

    /**
     * @var string Key前缀
     */
    public $keyPrefix = '';

    /**
     * @var bool 是否自动Hash Key
     */
    public $hashKey = false;

    /**
     * @var bool 是否启用PHP的自动序列化
     */
    public $phpSerialize = false;

    /**
     * @var bool 是否启用Redis的自动序列化
     */
    public $redisSerialize = false;

    /**
     * @var RedisAsynPool Redis异步连接池
     */
    public $redisAsynPool;

    /**
     * 初始化Redis异步请求的协程对象
     *
     * @param RedisAsynPool $redisAsynPool Redis连接池实例
     * @param string $name Redis指令
     * @param array $arguments Redis指令参数
     */
    public function __construct($redisAsynPool, $name, $arguments)
    {
        parent::__construct(6000);

        $this->redisAsynPool  = $redisAsynPool;
        $this->hashKey        = $redisAsynPool->hashKey;
        $this->phpSerialize   = $redisAsynPool->phpSerialize;
        $this->keyPrefix      = $redisAsynPool->keyPrefix;
        $this->redisSerialize = $redisAsynPool->redisSerialize;
        $this->name           = $name;
        $this->arguments      = $arguments;
        $this->request        = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) .  '#' . $this->redisAsynPool->getAsynName()  . '.' . $name;
        $this->requestId      = $this->getContext()->getRequestId();
        $requestId            = $this->requestId;

        $this->getContext()->getLog()->profileStart($this->request);
        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys            = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($result) use ($name, $requestId) {
            if (empty($this->getContext()) || ($requestId != $this->getContext()->getRequestId())) {
                return;
            }
            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
                return;
            }

            $this->getContext()->getLog()->profileEnd($this->request);

            switch ($name) {
                case 'mget':
                    $keys = $this->arguments[0];
                    $len = strlen($this->keyPrefix);
                    $result = $this->unSerializeHandler($result, $keys, $len);
                    break;
                case 'eval':
                    //如果redis中的数据本身没有进行序列化，同时返回值是json，那么解析成array
                    $decodeVal = @json_decode($result, true);
                    if (is_array($decodeVal)) {
                        $result = $decodeVal;
                    }
                    $result = $this->unSerializeHandler($result);
                    break;
                default:
                    $result = $this->unSerializeHandler($result);
            }

            $this->result = $result;
            $this->ioBack = true;
            $this->nextRun();
        });

        return $this;
    }

    /**
     * 发送异步的Redis请求
     *
     * @param callable $callback Redis指令执行后的回调函数
     */
    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->redisAsynPool->__call($this->name, $this->arguments);
    }

    /**
     * 反序列化
     *
     * @param mixed $data Redis响应数据
     * @param array $keys Redis操作的Key列表
     * @param int $len 操作的数据量
     * @return array|bool|mixed
     */
    protected function unSerializeHandler($data, $keys = [], $len = 0)
    {
        // 如果值是null，直接返回false
        if (null === $data) {
            return false;
        }

        if ('OK' === $data) {
            return $data;
        }

        try {
            //mget
            if (!empty($keys) && is_array($data)) {
                $ret = [];
                array_walk($data, function ($val, $k) use ($keys, $len, &$ret) {
                    if (!is_null($val)) {
                        $key = substr($keys[$k], $len);
                        $val = $this->realUnserialize($val);
                        $ret[$key] = $val;
                    }
                });

                $data = $ret;
            } elseif (is_array($data) && empty($keys)) {
                //eval sRandMember...
                $ret = [];
                array_walk($data, function ($val, $k) use (&$ret) {
                    if (is_array($val)) {
                        foreach ($val as $i => $v) {
                            $val[$i] = $this->realUnserialize($v);
                        }
                    } else {
                        $val = $this->realUnserialize($val);
                    }

                    $ret[$k] = $val;
                });

                $data = $ret;
            } else {
                //get
                $data = $this->realUnserialize($data);
            }
        } catch (\Exception $exception) {
            // do noting
        }

        return $data;
    }

    /**
     * 是否可以反序列化
     *
     * @param string $string 待反序列化的原始数据
     * @return bool
     */
    private function canUnserialize(string $string)
    {
        return in_array(substr($string, 0, 2), ['s:', 'i:', 'b:', 'N', 'a:', 'O:', 'd:']);
    }

    /**
     * 真正反序列化
     *
     * @param string $data 待反序列化的原始数据
     * @return mixed|string
     */
    private function realUnserialize($data)
    {
        //get
        if (is_string($data) && $this->redisSerialize) {
            switch ($this->redisSerialize) {
                case Macro::SERIALIZE_PHP:
                    if ($this->canUnserialize($data)) {
                        $data = unserialize($data);
                    }
                    break;
                case Macro::SERIALIZE_IGBINARY:
                    $data = @igbinary_unserialize($data);
                    break;
            }
        }

        if (is_string($data) && $this->phpSerialize) {
            switch ($this->phpSerialize) {
                case Macro::SERIALIZE_PHP:
                    if ($this->canUnserialize($data)) {
                        $data = unserialize($data);
                    }
                    break;
                case Macro::SERIALIZE_IGBINARY:
                    $data = @igbinary_unserialize($data);
                    break;
            }

            //兼容Yii逻辑
            if (is_array($data) && count($data) === 2 && array_key_exists(1, $data) && $data[1] === null) {
                $data = $data[0];
            }
        }
        return $data;
    }

    /**
     * 属性不用于序列化
     *
     * @return array
     */
    public function __unsleep()
    {
        return ['redisAsynPool'];
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
