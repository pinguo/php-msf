<?php
/**
 * Queue Beanstalk
 * @author    camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Queue;


use PG\MSF\Base\Core;
use PG\MSF\Coroutine\CTask;
use PG\MSF\Tasks\BeanstalkTask;
use Pheanstalk\Job;


/**
 *
 * 封装`Beanstalk`以适应php-msf.
 *
 * @see     https://github.com/pda/pheanstalk
 * @see     https://github.com/kr/beanstalkd
 *
 * @package PG\MSF\Queue
 */
class Beanstalk extends Core implements IQueue
{
    /**
     * @var BeanstalkTask
     */
    public $beanstalkTask;

    /**
     * Beanstalk constructor.
     *
     * @param string $configKey
     */
    public function __construct(string $configKey = '')
    {
        $this->beanstalkTask = $this->getObject(BeanstalkTask::class, [$configKey]);
    }

    /**
     * 入队.
     *
     * @param string $data  需要放入队列的数据.
     * @param string $queue Tube名字,具体请参见beanstalk文档.
     *
     * @return CTask
     */
    public function set(string $data, string $queue = 'default')
    {
        return $this->beanstalkTask->putInTube($queue, $data);
    }

    /**
     * 从队列中获取一个job,需要注意的是获取Job之后不会从队列中删除Job,需
     * 明确指定`$isAck=true`才会自动删除.
     *
     * @param string  $queue Tube名字.
     * @param boolean $isAck 是否从队列中删除,默认是.
     *
     * @return Job
     */
    public function get(string $queue = 'default', $isAck = true)
    {
        yield $this->beanstalkTask->useTube($queue);
        $job = yield $this->beanstalkTask->reserve();
        if ($isAck) {
            yield $this->beanstalkTask->delete($job);
        }

        return $job;
    }

}
