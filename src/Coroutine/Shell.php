<?php
/**
 * 异步协程shell_exec
 * 异步执行Shell命令。相当于shell_exec函数，执行后底层会fork一个子进程，并执行对应的command命令
 * 在Swoole1.9.22或更高版本可用
 * fork创建子进程的操作代价是非常昂贵的，系统无法支撑过大的并发量
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Coroutine;

class Shell extends Base
{
    /**
     * shell 指令
     * @var int
     */
    public $__command;

    /**
     * @param string $command shell命令
     * @return $this
     */
    public function goExec(string $command)
    {
        $this->__command   = $command;
        $this->requestId   = $this->getContext()->getRequestId();
        $requestId         = $this->requestId;

        getInstance()->scheduler->IOCallBack[$this->requestId][] = $this;
        $keys = array_keys(getInstance()->scheduler->IOCallBack[$this->requestId]);
        $this->ioBackKey = array_pop($keys);

        $this->send(function ($result, $status) use ($requestId) {
            if (empty($this->getContext()) || ($requestId != $this->getContext()->getRequestId())) {
                return;
            }

            if (empty(getInstance()->scheduler->taskMap[$this->requestId])) {
                return;
            }

            $this->result = $status === false ? false : $result;
            $this->ioBack = true;
            $this->nextRun();
        });

        return $this;
    }

    /**
     * 异步执行shell，并执行回调
     * @param callable $callback 回调函数
     * @return $this
     * @throws Exception
     */
    public function send($callback)
    {
        if (version_compare(SWOOLE_VERSION, '1.9.22', '<')) {
            throw new Exception('Swoole version must >= 1.9.22');
        }
        \Swoole\Async::exec($this->__command, $callback);
        return $this;
    }
}
