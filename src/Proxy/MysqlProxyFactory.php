<?php
/**
 * Mysql Proxy工厂类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

use PG\MSF\Macro;

/**
 * Class MysqlProxyFactory
 * @package PG\MSF\Proxy
 */
class MysqlProxyFactory
{
    /**
     * 生成proxy对象
     *
     * @param string $name Redis代理名称
     * @param array $config 配置对象
     * @return bool|mysqlProxyMasterSlave
     */
    public static function makeProxy(string $name, array $config)
    {
        $mode = $config['mode'];
        if ($mode == Macro::MASTER_SLAVE) {
            return new MysqlProxyMasterSlave($name, $config);
        } else {
            return false;
        }
    }
}
