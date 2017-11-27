<?php
/**
 * ISession 接口
 * 所有的适配器应该实现该接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Session;

/**
 * Interface ISession
 * @package PG\MSF\Session
 */
interface ISession
{
    /**
     * 关闭适配器
     * @return mixed
     */
    public function close();

    /**
     * 删除当前会话
     * @param string $sessionId 会话id
     * @return mixed
     */
    public function unset(string $sessionId);

    /**
     * gc
     * 可全量gc或某个会话
     * @param int $maxLifeTime
     * @param string $sessionId
     * @return mixed
     */
    public function gc(int $maxLifeTime, string $sessionId = '');

    /**
     * 初始化适配器
     * @param string $savePath session存储路径
     * @param string $sessionName session前缀
     * @return mixed
     */
    public function open(string $savePath, string $sessionName);

    /**
     * 读取session
     * @param string $sessionId 会话id
     * @return mixed
     */
    public function read(string $sessionId);

    /**
     * 写入session
     * @param string $sessionId 会话id
     * @param string $sessionData 会话内容
     * @return mixed
     */
    public function write(string $sessionId, string $sessionData);

    /**
     * 设定session的访问和修改时间
     * @param string $sessionId
     * @return mixed
     */
    public function touch(string $sessionId);
}
