<?php
/**
 * @desc:
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/4/12
 * @copyright All rights reserved.
 */

namespace PG\MSF\Proxy;

use PG\MSF\Marco;

class RedisProxyFactory
{
    public static $redisCoroutines = [];

    /**
     * 生成proxy对象
     * @param $name
     * @param $config
     * @return bool|RedisProxyCluster|RedisProxyMasterSlave
     */
    public static function makeProxy($name, $config)
    {
        $mode = $config['mode'];
        if ($mode == Marco::CLUSTER) {
            return new RedisProxyCluster($name, $config);
        } elseif ($mode == Marco::MASTER_SLAVE) {
            return new RedisProxyMasterSlave($name, $config);
        } else {
            return false;
        }
    }

    public static function getLogTitle()
    {
        return "\n" . 'Redis Proxy [ ' . date('Y-m-d H:i:s') . ' ] : ';
    }
}
