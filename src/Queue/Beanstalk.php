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
     * @param int    $delay 延迟ready的秒数,在这段时间job为delayed状态.默认不延迟
     *
     * @return CTask
     */
    public function set(string $data, string $queue = 'default', $delay = 0)
    {
        return $this->beanstalkTask->putInTube($queue, $data, null, $delay);
    }

    /**
     * 从队列中获取一个job,需要注意的是获取Job之后不会从队列中删除Job,需
     * 明确指定`$isAck=true`才会自动删除.
     *
     * @param string   $queue   Tube名字(默认会watch此tube).
     * @param boolean  $isAck   是否从队列中删除,默认是.
     * @param int|null $timeout 取Job的超时时间,即`reserve-with-timeout`.
     *
     * @return false|Job
     */
    public function get(string $queue = 'default', $isAck = true, $timeout = null)
    {
        yield $this->beanstalkTask->watch($queue);
        $job = yield $this->beanstalkTask->reserve($timeout);
        if ($job && $isAck) {
            yield $this->beanstalkTask->delete($job);
        }

        return $job;
    }

}
