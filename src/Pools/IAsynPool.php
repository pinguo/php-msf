<?php
/**
 * IAsynPool
 *
 * @author tmtbe
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pools;

use PG\MSF\MSFServer;
use Exception;

/**
 * Interface IAsynPool
 * @package PG\MSF\Pools
 */
interface IAsynPool
{
    /**
     * 返回唯一的连接池名称
     *
     * @return string
     */
    public function getAsynName();

    /**
     * 分发消息
     *
     * @param array $data 待分发数据
     * @return $this
     */
    public function distribute($data);

    /**
     * 执行命令
     *
     * @param array $data 命令相关信息
     */
    public function execute($data);

    /**
     * 初始化
     *
     * @param MSFServer $swooleServer Server实例
     * @param AsynPoolManager $asynManager 异步连接池管理器
     * @return $this
     */
    public function serverInit($swooleServer, $asynManager);

    /**
     * 初始化workerId
     *
     * @param int $workerId worker进程ID
     * @return $this
     */
    public function workerInit($workerId);

    /**
     * 归还连接
     *
     * @param mixed $client 连接对象
     * @return $this
     */
    public function pushToPool($client);

    /**
     * 创建连接
     */
    public function prepareOne();

    /**
     * 注册回调
     *
     * @param callable $callback 回调函数
     * @return int
     */
    public function addTokenCallback($callback);

    /**
     * 获取同步连接
     *
     * @return mixed
     * @throws Exception
     */
    public function getSync();

    /**
     * 建立连接
     *
     * @param null $client
     * @return mixed
     */
    public function reconnect($client = null);
}
