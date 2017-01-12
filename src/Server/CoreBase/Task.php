<?php
namespace Server\CoreBase;
/**
 * Task 异步任务
 * 在worker中的Task会被构建成TaskProxy。这个实例是单例的，
 * 所以发起task请求时每次都要使用loader给TaskProxy赋值，不能缓存重复使用，以免数据错乱。
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午12:00
 */
class Task extends TaskProxy
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * sendToUid
     * @param $uid
     * @param $data
     */
    protected function sendToUid($uid, $data)
    {
        $data = $this->pack->pack($data);
        get_instance()->sendToUid($uid, $data);
    }

    /**
     * sendToUids
     * @param $uids
     * @param $data
     */
    protected function sendToUids($uids, $data)
    {
        $data = $this->pack->pack($data);
        get_instance()->sendToUids($uids, $data);
    }

    /**
     * sendToAll
     * @param $data
     */
    protected function sendToAll($data)
    {
        $data = $this->pack->pack($data);
        get_instance()->sendToAll($data);
    }

    /**
     * 获取同步redis
     * @return \Redis
     * @throws SwooleException
     */
    protected function getRedis()
    {
        return get_instance()->getRedis();
    }

    /**
     * 获取同步mysql
     * @return \Server\DataBase\Miner
     */
    protected function getMysql()
    {
        return get_instance()->getMysql();
    }
}