<?php
/**
 * @desc:
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/12
 * @copyright All rights reserved.
 */

namespace PG\MSF\Server\Proxy;

use PG\MSF\Server\Marco;

class RedisProxyFactory
{
    public static $redisCoroutines = [];

    /**
     * 生成proxy对象
     * @param $config
     * @return bool|RedisProxyCluster|RedisProxyMasterSlave
     */
    public static function makeProxy($config)
    {
        $model = $config['model'];
        if ($model == Marco::CLUSTER) {
            echo "cluster\n";
            return new RedisProxyCluster($config);
        } elseif ($model == Marco::MASTER_SLAVE) {
            echo "master-slave\n";
            return new RedisProxyMasterSlave($config);
        } else {
            return false;
        }
    }

    public static function getLogTitle()
    {
        return "\n" . 'Redis Proxy [ ' . date('Y-m-d H:i:s') . ' ] : ';
    }
}
