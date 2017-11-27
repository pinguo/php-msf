<?php
/**
 * Redis Proxy工厂类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

use PG\MSF\Macro;

/**
 * Class RedisProxyFactory
 * @package PG\MSF\Proxy
 */
class RedisProxyFactory
{
    /**
     * @var array Redis协程
     */
    public static $redisCoroutines = [];

    /**
     * 生成proxy对象
     *
     * @param string $name Redis代理名称
     * @param array $config 配置对象
     * @return bool|RedisProxyCluster|RedisProxyMasterSlave
     */
    public static function makeProxy(string $name, array $config)
    {
        $mode = $config['mode'];
        if ($mode == Macro::CLUSTER) {
            return new RedisProxyCluster($name, $config);
        } elseif ($mode == Macro::MASTER_SLAVE) {
            return new RedisProxyMasterSlave($name, $config);
        } else {
            return false;
        }
    }
}
