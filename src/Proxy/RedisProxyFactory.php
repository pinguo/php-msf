<?php
/**
 * Redis Proxy工厂类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

use PG\MSF\Marco;

class RedisProxyFactory
{
    /**
     * @var array Redis协程
     */
    public static $redisCoroutines = [];

    /**
     * 生成proxy对象
     *
     * @param string $name
     * @param array $config
     * @return bool|RedisProxyCluster|RedisProxyMasterSlave
     */
    public static function makeProxy(string $name, array $config)
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

    /**
     * 返回日志前缀
     *
     * @return string
     */
    public static function getLogTitle()
    {
        return "\n" . 'Redis Proxy [ ' . date('Y-m-d H:i:s') . ' ] : ';
    }
}
