<?php
/**
 * @desc: Redis proxy
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/10
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Proxy;

use Flexihash\{
    Flexihash, Hasher\Md5Hasher
};
use PG\MSF\Server\Marco;

class RedisProxy
{
    public static $redisCoroutines = [];
    private $config;
    private $proxy;

    public function __construct($config)
    {
        $this->config = $config;

        $model = $config['model'];
        $pools = $config['pools'];

        if ($model == Marco::CLUSTER) {
            $hasher = $config['hasher'] ?? Md5Hasher::class;
            $hasher = new $hasher;

            $this->proxy = new Flexihash($hasher);
            foreach ($pools as $pool => $weight) {
                $this->proxy->addTarget($pool, $weight);
            }
        } else {
            $this->proxy = new class
            {
                private $pools;
                private $master;
                private $slaves;

                public function set($key, $value)
                {
                    $this->{$key} = $value;
                    return true;
                }

                public function get($key)
                {
                    return $this->{$key};
                }
            };

            $this->proxy->set('pools', $pools);

            $master = '';
            foreach ($pools as $redisPoolName) {
                $redisPoolSync = getInstance()->getAsynPool($redisPoolName)->getSync();
                if ($redisPoolSync->set('msf_rw', 1, 30) === true) {
                    $master = $redisPoolName;
                    $this->proxy->set('master', $redisPoolName);
                    echo "Redis Master: {$redisPoolName} found \n";
                    break;
                }
            }
            if ($master === '') {
                echo 'Redis No master found in ( ' . implode(',', $pools) . " ) , please check! \n";
                $this->proxy = null;
                return false;
            }

            $slaves = [];
            foreach ($pools as $redisPoolName) {
                if ($redisPoolName != $master) {
                    $redisPoolSync = getInstance()->getAsynPool($redisPoolName)->getSync();
                    if ($redisPoolSync->get('msf_rw') == 1) {
                        $slaves[] = $redisPoolName;
                    }
                }
            }

            if (empty($slaves)) {
                echo 'Redis No slave found in ( ' . implode(',', $pools) . " ) , please check! \n";
                $this->proxy = null;
                return false;
            } else {
                $this->proxy->set('slaves', $slaves);
                echo 'Redis Slaves: ( ' . implode(',', $slaves) . " ) found \n";
            }
        }
    }

    public function getProxy()
    {
        return $this->proxy;
    }

    public function refreshProxy()
    {
        //todo: 删除无效连接池
    }
}
