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
    function getAsynName();

    /**
     * 分发消息
     *
     * @param array $data 待分发数据
     * @return $this
     */
    function distribute($data);

    /**
     * 执行命令
     *
     * @param array $data 命令相关信息
     */
    function execute($data);

    /**
     * 初始化
     *
     * @param MSFServer $swooleServer Server实例
     * @param AsynPoolManager $asynManager 异步连接池管理器
     * @return $this
     */
    function serverInit($swooleServer, $asynManager);

    /**
     * 初始化workerId
     *
     * @param int $workerId worker进程ID
     * @return $this
     */
    function workerInit($workerId);

    /**
     * 归还连接
     *
     * @param mixed $client 连接对象
     * @return $this
     */
    function pushToPool($client);

    /**
     * 创建连接
     */
    function prepareOne();

    /**
     * 注册回调
     *
     * @param callable $callback 回调函数
     * @return int
     */
    function addTokenCallback($callback);

    /**
     * 获取同步连接
     *
     * @return mixed
     * @throws Exception
     */
    function getSync();
}
