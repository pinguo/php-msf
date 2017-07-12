<?php
/**
 * Redis
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

use PG\MSF\DataBase\RedisAsynPool;
use PG\MSF\Marco;

class Redis extends Base
{
    /**
     * Redis操作指令
     * @var string
     */
    public $name;

    /**
     * Redis操作指令的参数
     *
     * @var array
     */
    public $arguments;

    /**
     * Key前缀
     *
     * @var string
     */
    public $keyPrefix = '';

    /**
     * 是否自动Hash Key
     *
     * @var bool
     */
    public $hashKey = false;

    /**
     * 是否启用PHP的自动序列化
     *
     * @var bool
     */
    public $phpSerialize = false;

    /**
     * 是否启用Redis的自动序列化
     *
     * @var bool
     */
    public $redisSerialize = false;

    /**
     * Redis异步连接池
     *
     * @var RedisAsynPool
     */
    public $redisAsynPool;

    /**
     * 初始化Redis异步请求的协程对象
     *
     * @param RedisAsynPool $redisAsynPool
     * @param string $name
     * @param array $arguments
     * @return $this
     */
    public function initialization($redisAsynPool, $name, $arguments)
    {
        parent::init(1000);

        $this->redisAsynPool  = $redisAsynPool;
        $this->hashKey        = $redisAsynPool->hashKey;
        $this->phpSerialize   = $redisAsynPool->phpSerialize;
        $this->keyPrefix      = $redisAsynPool->keyPrefix;
        $this->redisSerialize = $redisAsynPool->redisSerialize;

        $this->name      = $name;
        $this->arguments = $arguments;
        $this->request   = mt_rand(1, 9) . mt_rand(1, 9) . mt_rand(1, 9) . "#redis.$name";
        $this->requestId = $this->getContext()->getLogId();

        $this->getContext()->getLog()->profileStart($this->request);
        getInstance()->coroutine->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->coroutine->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($result) use ($name) {
            if ($this->isBreak) {
                return;
            }

            if (empty(getInstance()->coroutine->taskMap[$this->requestId])) {
                return;
            }

            $this->getContext()->getLog()->profileEnd($this->request);

            switch ($name) {
                case 'get':
                    $result = $this->unSerializeHandler($result);
                    break;
                case 'mget';
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
     * @param $callback
     */
    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->redisAsynPool->__call($this->name, $this->arguments);
    }

    /**
     * 反序列化
     * @param $data
     * @param array $keys
     * @param int $len
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
                    $key = substr($keys[$k], $len);

                    if (is_string($val)) {
                        switch ($this->redisSerialize) {
                            case Marco::SERIALIZE_PHP:
                                if ($this->canUnserialize($val)) {
                                    $val = unserialize($val);
                                }
                                break;
                            case Marco::SERIALIZE_IGBINARY:
                                $val = @igbinary_unserialize($val);
                                break;
                        }
                    }

                    if (is_string($val) && $this->phpSerialize) {
                        switch ($this->phpSerialize) {
                            case Marco::SERIALIZE_PHP:
                                if ($this->canUnserialize($val)) {
                                    $val = unserialize($val);
                                }
                                break;
                            case Marco::SERIALIZE_IGBINARY:
                                $val = @igbinary_unserialize($val);
                                break;
                        }

                        //兼容yii逻辑
                        if (is_array($val) && count($val) === 2 && array_key_exists(1, $val) && $val[1] === null) {
                            $val = $val[0];
                        }
                    }

                    $ret[$key] = $val;
                });

                $data = $ret;
            } elseif (is_array($data) && empty($keys)) {
                //eval sRandMember...
                $ret = [];
                array_walk($data, function ($val, $k) use (&$ret) {
                    if (is_string($val)) {
                        switch ($this->redisSerialize) {
                            case Marco::SERIALIZE_PHP:
                                if ($this->canUnserialize($val)) {
                                    $val = unserialize($val);
                                }
                                break;
                            case Marco::SERIALIZE_IGBINARY:
                                $val = @igbinary_unserialize($val);
                                break;
                        }
                    }

                    //兼容yii逻辑
                    if (is_array($val) && count($val) === 2 && array_key_exists(1, $val) && $val[1] === null) {
                        $val = $val[0];
                    }

                    $ret[$k] = $val;
                });

                $data = $ret;
            } else {
                //get
                if (is_string($data) && $this->redisSerialize) {
                    switch ($this->redisSerialize) {
                        case Marco::SERIALIZE_PHP:
                            if ($this->canUnserialize($data)) {
                                $data = unserialize($data);
                            }
                            break;
                        case Marco::SERIALIZE_IGBINARY:
                            $data = @igbinary_unserialize($data);
                            break;
                    }
                }

                if (is_string($data) && $this->phpSerialize) {
                    switch ($this->phpSerialize) {
                        case Marco::SERIALIZE_PHP:
                            if ($this->canUnserialize($data)) {
                                $data = unserialize($data);
                            }
                            break;
                        case Marco::SERIALIZE_IGBINARY:
                            $data = @igbinary_unserialize($data);
                            break;
                    }

                    //兼容yii逻辑
                    if (is_array($data) && count($data) === 2 && array_key_exists(1, $data) && $data[1] === null) {
                        $data = $data[0];
                    }
                }
            }
        } catch (\Exception $exception) {
            // do noting
        }

        return $data;
    }

    /**
     * 是否可以反序列化
     * @param string $string
     * @return bool
     */
    private function canUnserialize(string $string)
    {
        $head = substr($string, 0, 2);
        return in_array($head, ['s:', 'i:', 'b:', 'N', 'a:', 'O:', 'd:']);
    }

    /**
     * 属性不用于序列化
     *
     * @return array
     */
    public function __unsleep()
    {
        return ['context', 'redisAsynPool'];
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
    }
}
