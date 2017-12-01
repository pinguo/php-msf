<?php
/**
 * Queue Beanstalk
 * @author    camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Tasks;

use Pheanstalk\Command\PeekCommand;
use Pheanstalk\Connection;
use Pheanstalk\Exception\ClientException;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Pheanstalk\Response;

/**
 * Beanstalk Task, 投递给Swoole Task的耗时任务.
 *
 * @see     https://github.com/pda/pheanstalk
 * @see     https://github.com/kr/beanstalkd
 *
 * @package PG\MSF\Tasks
 */
class BeanstalkTask extends Task
{

    /**
     * @var string configKey
     */
    protected $configKey = '';

    /**
     * @var array 加载的全局配置.
     */
    protected $beanstalkConfig;

    /**
     * @var Pheanstalk
     */
    protected $client;

    /**
     * BeanstalkTask constructor.
     *
     * @param string $configKey
     *
     * @throws ClientException
     */
    public function __construct($configKey = '')
    {
        if ($configKey) {
            $this->configKey = $configKey;
        }
        if (!is_object($this->client)) {
            $this->prepare($this->configKey);
        }
        parent::__construct();
    }

    /**
     * 获取默认的配置信息.
     *
     * @see getBeanstalkConf
     *
     * @return array
     */
    public function getDefaultBeanstalkConf()
    {
        return [
            'host' => '127.0.0.1',
            'port' => PheanstalkInterface::DEFAULT_PORT,
            'connectTimeout' => Connection::DEFAULT_CONNECT_TIMEOUT,
            'connectPersistent' => true,
            'tube' => PheanstalkInterface::DEFAULT_TUBE,
        ];
    }

    /**
     * 获取配置信息.
     *
     * @see getDefaultBeanstalkConf
     *
     * @return array
     */
    public function getBeanstalkConf()
    {
        return $this->beanstalkConfig;
    }

    /**
     * 初始化准备工作.
     *
     * @param $configKey
     */
    protected function prepare($configKey)
    {
        $config = getInstance()->config->get($configKey, []);
        $this->beanstalkConfig = $config = array_replace($this->getDefaultBeanstalkConf(), $config);
        $this->client = new Pheanstalk($config['host'], $config['port'], $config['connectTimeout'], $config['connectPersistent']);
        $this->useTube($config['tube']);
    }

    /**
     * Producer生产者使用，随后使用put命令，将job放置于对应的tube格式.
     * @see put
     * @see putInTube
     *
     * @param string $tube Tube的名字.
     *
     * @return bool
     */
    public function useTube($tube)
    {
        $this->client->useTube($tube);

        return true;
    }

    /**
     * 插入一个job到队列
     *
     * @see https://github.com/kr/beanstalkd/blob/master/doc/protocol.zh-CN.md#生产者指令说明producer-commands
     *
     * @param     $data
     * @param int $priority
     * @param int $delay
     * @param int $ttr
     *
     * @return int
     */
    public function put($data,
                        $priority = null,
                        $delay = null,
                        $ttr = null)
    {
        $priority = $priority ?? PheanstalkInterface::DEFAULT_PRIORITY;
        $delay = $delay ?? PheanstalkInterface::DEFAULT_DELAY;
        $ttr = $ttr ?? PheanstalkInterface::DEFAULT_TTR;

        return $this->client->put($data, $priority, $delay, $ttr);
    }

    /**
     * 插入一个job到队列(指定useTube.)
     * 需要注意的是当useTube之后默认就任务修改了tube.不会再做切回操作.
     *
     * @see useTube
     * @see put
     *
     * @param     $tube
     * @param     $data
     * @param int $priority
     * @param int $delay
     * @param int $ttr
     *
     * @return int
     */
    public function putInTube(
        $tube,
        $data,
        $priority = null,
        $delay = null,
        $ttr = null
    )
    {
        $this->useTube($tube);
        $priority = $priority ?? PheanstalkInterface::DEFAULT_PRIORITY;
        $delay = $delay ?? PheanstalkInterface::DEFAULT_DELAY;
        $ttr = $ttr ?? PheanstalkInterface::DEFAULT_TTR;

        return $this->put($data, $priority, $delay, $ttr);
    }

    /**
     * 将一个reserved的job放回ready queue。它通常在job执行失败时使用.
     *
     * @param Job $job
     * @param int $priority
     * @param int $delay
     *
     * @return Pheanstalk
     */
    public function release(
        Job $job,
        $priority = null,
        $delay = null
    )
    {
        $priority = $priority ?? PheanstalkInterface::DEFAULT_PRIORITY;
        $delay = $delay ?? PheanstalkInterface::DEFAULT_DELAY;

        return $this->client->release($job, $priority, $delay);
    }

    /**
     * 删除一个Job.
     *
     * @param Job $job
     *
     * @return Pheanstalk
     */
    public function delete(Job $job)
    {
        return $this->client->delete($job);
    }

    /**
     * 取出job，待处理.
     * 它将返回一个新预订的job，如果没有job，beanstalkd将直到有job时才发送响应.
     * 注意: 超时时间不能大于协程的调度时间,否则会失败.
     *
     * @param null $timeout 取Job的超时时间,即`reserve-with-timeout`.
     *
     * @return false|Job
     */
    public function reserve($timeout = null)
    {
        return $this->client->reserve($timeout);
    }

    /**
     * 将一个job的状态迁移为buried(通过kick命令唤醒)
     *
     * @see kickJob
     *
     * @param Job $job      需要迁移的Job.
     * @param int $priority 优先级.
     *
     * @return true
     */
    public function bury(Job $job, $priority = PheanstalkInterface::DEFAULT_PRIORITY)
    {
        $this->client->bury($job, $priority);

        return true;
    }

    /**
     * 应用在当前使用的tube中，它将job的状态迁移为ready或者delayed.
     *
     * @param Job $job 需要kick的Job对象.
     *
     * @return Pheanstalk
     */
    public function kickJob(Job $job)
    {
        return $this->client->kickJob($job);
    }

    /**
     * 允许worker请求更多的时间执行job，当job需要很长的时间来执行时这个很有用
     *
     * @param Job $job
     *
     * @return Pheanstalk
     */
    public function touch(Job $job)
    {
        return $this->client->touch($job);
    }

    /**
     * 添加监控的tube到watch list列表，reserve指令将会从监控的tube列表获取job.
     *
     * @param string $tube
     *
     * @return Pheanstalk
     */
    public function watch(string $tube)
    {
        return $this->client->watch($tube);
    }

    /**
     * 从已监控的watch list列表中移出特定的tube.
     *
     * @param string $tube
     *
     * @return Pheanstalk
     */
    public function ignore(string $tube)
    {
        return $this->client->ignore($tube);
    }

    /**
     * 让client在系统中检查job.分成四种:
     *  1. peek 返回id对应的job
     *  2. peekReady 返回下一个ready job
     *  3. peekDelayed 返回下一个延迟剩余时间最短的job
     *  4. peekBuried  返回下一个在buried列表中的job
     *  即参数`type`可指定为id,ready,delayed,buried
     *  可选参数`tubeOrId`可以tube名或者jobId.
     *  当type为除开id外的其他三种情况,tubeOrId表示`jobId`,否则表示`tube`.
     *
     * @see peek
     *
     * @param string     $type     需要peek的类型
     * @param string|int $tubeOrId Tube名或jobId,受参数`type`影响.
     *
     * @return Job
     */
    public function peekJobByType($type = null, $tubeOrId = null)
    {
        switch (true) {
            case $type === PeekCommand::TYPE_BURIED:
                $job = $this->client->peekBuried($tubeOrId);
                break;
            case $type === PeekCommand::TYPE_DELAYED:
                $job = $this->client->peekDelayed($tubeOrId);
                break;
            case $type === PeekCommand::TYPE_READY:
                $job = $this->client->peekReady($tubeOrId);
                break;
            case $type === PeekCommand::TYPE_ID:
                $job = $this->client->peek($tubeOrId);
                break;
            default:
                $job = $this->client->peek($tubeOrId);
        }

        return $job;
    }

    /**
     * 返回id对应的job.
     *
     * @see peekJobByType
     *
     * @param int $jobId Job的Id.
     *
     * @return Job
     */
    public function peek(int $jobId)
    {
        return $this->client->peek($jobId);
    }

    /**
     * 统计job的相关信息.
     *
     * @param Job $job
     *
     * @return Response
     */
    public function statsJob(Job $job)
    {
        return $this->client->statsJob($job);
    }

    /**
     * 返回整个消息队列系统的整体信息.
     *
     * @return Response
     */
    public function stats()
    {
        return $this->client->stats();
    }

    /**
     * 统计tube的相关信息.
     *
     * @param string $tube
     *
     * @return Response
     */
    public function statsTube(string $tube)
    {
        return $this->client->statsTube($tube);
    }

    /**
     * 列表所有存在的tube.
     *
     * @return array
     */
    public function listTubes()
    {
        return $this->client->listTubes();
    }

    /**
     * @param bool $askServer
     *
     * @return array
     */
    public function listTubesWatched($askServer = false)
    {
        return $this->client->listTubesWatched($askServer);
    }

    /**
     * @param bool $askServer
     *
     * @return string
     */
    public function listTubeUsed($askServer = false)
    {
        return $this->client->listTubeUsed($askServer);
    }

    /**
     * 恢复tube.
     *
     * @see pauseTube
     *
     * @param string $tube
     *
     * @return true
     */
    public function resumeTube($tube)
    {
        $this->client->resumeTube($tube);

        return true;
    }

    /**
     * 临时暂停从给定的tube中reserve出job.
     *
     * @param string $tube  Tube名字.
     * @param int    $delay 延迟时间.0表示恢复.
     *
     * @return true
     */
    public function pauseTube($tube, $delay)
    {
        $this->client->pauseTube($tube, $delay);

        return true;
    }

    /**
     * @param string $tube
     * @param int    $timeout
     *
     * @return false|Job
     */
    public function reserveFromTube($tube, $timeout = null)
    {
        return $this->client->reserveFromTube($tube, $timeout);
    }

}
