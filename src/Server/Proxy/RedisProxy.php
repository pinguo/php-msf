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
        $hasher = $config['hasher'] ?? Md5Hasher::class;

        $hasher = new $hasher;

        if ($model == Marco::CLUSTER) {
            $this->proxy = new Flexihash($hasher);
            foreach ($pools as $pool => $weight) {
                $this->proxy->addTarget($pool, $weight);
            }
        } else {
            //todo: master-slave
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
