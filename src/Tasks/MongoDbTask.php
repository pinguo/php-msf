<?php
/**
 * @desc: MongoDB Task 基类
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/2/13
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Tasks;

use PG\MSF\Base\Task;

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
     * @var \MongoDB
     */
    public $mongoDb;
    /**
     * @var \MongoCollection
     */
    public $mongoCollection;

    /**
     * @var string
     */
    private $profileName = '';

    /**
     * __construct之后执行，仅一次
     * @throws \MongoConnectionException
     */
    public function afterConstruct()
    {
        if (empty($this->mongoConf)) {
            throw new \MongoConnectionException('No $mongoConf in this class or no server config in $mongoConf');
        }
        $this->prepare($this->mongoConf[0], $this->mongoConf[1] ?? '', $this->mongoConf[2] ?? '');
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
        $this->profileName = 'mongo.' . $db . '.';
        $this->config = getInstance()->config['mongodb'] ?? [];
        if (!isset($this->config[$confKey])) {
            throw new \MongoConnectionException('No such a MongoDB config ' . $confKey);
        }
        $conf = $this->config[$confKey];
        $this->mongoClient = new \MongoClient($conf['server'], $conf['options'], $conf['driverOptions']);
        $db && ($this->mongoDb = $this->mongoClient->selectDB($db));
        $collection && ($this->mongoCollection = $this->mongoDb->selectCollection($collection));
    }

    /**
     * 独立选择db和collection
     * @param string $dbName
     * @param string $collectionName
     * @return \MongoCollection
     */
    public function setDbCollection(string $dbName, string $collectionName): \MongoCollection
    {
        return $this->mongoClient->selectDB($dbName)->selectCollection($collectionName);
    }

    /**
     * 查询文档，直接返回数据而不是MongoCursor对象。由于是直接返回数据，在查询结果集非常大时一定要指定limit。
     * @param array $query
     * @param array $fields
     * @param array $sort
     * @param int $limit
     * @param int $skip
     * @param int $timeout default 10s, 0 wait forever.
     * @return array:
     */
    public function query(
        $query = [],
        $fields = [],
        $sort = null,
        $limit = null,
        $skip = null,
        $timeout = 2000
    ) {
        $this->PGLog->profileStart($this->profileName . __FUNCTION__);
        $cursor = $this->mongoCollection->find($query, $fields);
        if (!is_null($sort)) {
            $cursor->sort($sort);
        }
        if (!is_null($limit)) {
            $cursor->limit($limit);
        }
        if (!is_null($skip)) {
            $cursor->skip($skip);
        }
        $cursor->maxTimeMS($timeout);
        $out = iterator_to_array($cursor);
        $this->PGLog->profileEnd($this->profileName . __FUNCTION__);

        return $out;
    }

    /**
     * 新建文档，简化MongoDB驱动的insert调用
     * @param array $doc
     * @param int $timeout
     * @param int $w
     * @param boolean $fsync
     * @return boolean
     */
    public function add($doc, $timeout = 5000, $w = 1, $fsync = false)
    {
        $this->PGLog->profileStart($this->profileName . __FUNCTION__);
        $options = [
            'w' => $w,
            'fsync' => $fsync,
            'socketTimeoutMS' => $timeout,
        ];
        $ret = $this->mongoCollection->insert($doc, $options);
        $this->PGLog->profileEnd($this->profileName . __FUNCTION__);
        if ($w > 0) {
            if ($ret['ok'] && is_null($ret['err'])) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * 批量新建文档，简化MongoDB驱动的batchInsert调用
     * @param array $docs
     * @param boolean $continueOnError
     * @param int $timeout
     * @param int $w
     * @param boolean $fsync
     * @return boolean
     */
    public function batchAdd(
        $docs,
        $continueOnError = true,
        $timeout = 2000,
        $w = 1,
        $fsync = false
    ) {
        $this->PGLog->profileStart($this->profileName . __FUNCTION__);
        $options = [
            'w' => $w,
            'fsync' => $fsync,
            'continueOnError' => $continueOnError,
            'socketTimeoutMS' => $timeout,
        ];
        $ret = $this->mongoCollection->batchInsert($docs, $options);
        $this->PGLog->profileEnd($this->profileName . __FUNCTION__);
        if ($w > 0) {
            if ($ret['ok'] && is_null($ret['err'])) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * 修改文档，简化MongoDB驱动自带的update使用，类似MySQL的更新接口。
     * @param array $criteria 更新条件
     * @param array $doc 要更新的字段和值
     * @param boolean $multiple 是否更新所有符合条件的文档
     * @param boolean $upsert 没有符合条件的文档时，是否插入新文档
     * @param int $timeout 超时时间，单位ms
     * @param int $w 成功写入到多少个复制时返回
     * @param boolean $fsync 是否等待MongoDB将数据更新到磁盘
     * @return boolean
     */
    public function modify(
        $criteria,
        $doc,
        $multiple = true,
        $upsert = false,
        $timeout = 2000,
        $w = 1,
        $fsync = false
    ) {
        $this->PGLog->profileStart($this->profileName . __FUNCTION__);

        $options = [
            'w' => $w,
            'fsync' => $fsync,
            'upsert' => $upsert,
            'multiple' => $multiple,
            'socketTimeoutMS' => $timeout,
        ];
        $ret = $this->mongoCollection->update($criteria, ['$set' => $doc], $options);
        $this->PGLog->profileEnd($this->profileName . __FUNCTION__);
        if ($w > 0) {
            if ($ret['ok'] && is_null($ret['err'])) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * 修改文档
     * @param array $criteria 更新条件
     * @param array $doc 要更新的字段和值
     * @param boolean $multiple 是否更新所有符合条件的文档
     * @param boolean $upsert 没有符合条件的文档时，是否插入新文档
     * @param int $timeout 超时时间，单位ms
     * @param int $w 成功写入到多少个复制时返回
     * @param boolean $fsync 是否等待MongoDB将数据更新到磁盘
     * @return boolean
     */
    public function updateDoc(
        $criteria,
        $doc,
        $multiple = true,
        $upsert = false,
        $timeout = 2000,
        $w = 1,
        $fsync = false
    ) {
        $this->PGLog->profileStart($this->profileName . __FUNCTION__);
        $options = [
            'w' => $w,
            'fsync' => $fsync,
            'upsert' => $upsert,
            'multiple' => $multiple,
            'socketTimeoutMS' => $timeout,
        ];
        $ret = $this->mongoCollection->update($criteria, $doc, $options);
        $this->PGLog->profileEnd($this->profileName . __FUNCTION__);
        if ($w > 0) {
            if ($ret['ok'] && is_null($ret['err'])) {
                return $ret['n'];
            } else {
                $this->PGLog->error('update failed. criteria:' . json_encode($criteria) . ' doc:' . json_encode($doc) . ' err:' . $ret['err']);

                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * 删除文档，简化MongoDB驱动自带的remove使用，类似MySQL的删除接口。
     * @param array $criteria 删除条件
     * @param boolean $justOne 是否只删除符合条件的第一条
     * @param int $timeout 超时时间，单位ms
     * @param int $w 成功写入到多少个复制时返回
     * @param boolean $fsync 是否等待MongoDB将数据更新到磁盘
     * @return boolean
     */
    public function delete(
        $criteria,
        $justOne = false,
        $timeout = 5000,
        $w = 1,
        $fsync = false
    ) {
        $this->PGLog->profileStart($this->profileName . __FUNCTION__);
        $options = [
            'justOne' => $justOne,
            'w' => $w,
            'fsync' => $fsync,
            'socketTimeoutMS' => $timeout,
        ];
        $ret = $this->mongoCollection->remove($criteria, $options);
        $this->PGLog->profileEnd($this->profileName . __FUNCTION__);
        if ($w > 0) {
            if ($ret['ok'] && is_null($ret['err'])) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * 对当前Collection所在Database上执行command
     * @param $command
     * @param int $timeout 超时时间，单位ms
     * @return bool
     */
    public function command($command, $timeout = 5000)
    {
        $this->PGLog->profileStart($this->profileName . __FUNCTION__);
        $result = $this->mongoDb->command($command, ['socketTimeoutMS' => $timeout]);
        $this->PGLog->profileEnd($this->profileName . __FUNCTION__);
        if ($result['ok'] == 1) {
            return $result['results'];
        } else {
            $this->PGLog->error("mongo command failed: command-" . json_encode($command) . " result-"
                . json_encode($result));

            return false;
        }
    }
}
