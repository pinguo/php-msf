<?php
/**
 * @desc: MongoDB Task 基类
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/2/13
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Tasks;

use PG\MSF\Server\CoreBase\Task;

class MongoDbTask extends Task
{
    /**
     * 当前要使用的MongoDB配置
     * 配置名，db名，collection名
     * @var array
     */
    public $mongoConf = [];
    /**
     * 全局MongoDB配置
     * @var array
     */
    public $config;
    /**
     * @var \MongoClient
     */
    public $mongoClient;
    /**
     * @var \MongoCollection
     */
    public $mongoCollection;

    /**
     * __construct之后执行，仅一次
     * @throws \MongoConnectionException
     */
    public function afterConstruct()
    {
        if (empty($this->mongoConf) || count($this->mongoConf) != 3) {
            throw new \MongoConnectionException('No $mongoConf in this class');
        }

        $this->prepare($this->mongoConf[0], $this->mongoConf[1], $this->mongoConf[2]);
        parent::afterConstruct();
    }

    /**
     * 初始化链接 每个task进程内只初始化一次
     * @param string $confKey
     * @param string $db
     * @param string $collection
     * @throws \MongoConnectionException
     */
    public function prepare(string $confKey, string $db, string $collection)
    {
        $this->config = get_instance()->config['mongodb'] ?? [];
        if (!isset($this->config[$confKey])) {
            throw new \MongoConnectionException('No such a MongoDB config '.$confKey);
        }
        $conf = $this->config[$confKey];
        $this->mongoClient = new \MongoClient($conf['server'], $conf['options'], $conf['driverOptions']);

        $this->mongoCollection = $this->mongoClient->selectDB($db)->selectCollection($collection);
    }
}
