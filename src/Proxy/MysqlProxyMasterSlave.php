<?php
/**
 * 主从结构MySQL代理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Proxy;

use PG\AOP\Wrapper;
use PG\MSF\Helpers\Context;
use PG\MSF\Pools\Miner;
use PG\MSF\Pools\MysqlAsynPool;

/**
 * class MysqlProxyMasterSlave
 * @package PG\MSF\Proxy
 */
class MysqlProxyMasterSlave implements IProxy
{
    /**
     * @var string 代理标识，它代表一个Mysql集群
     */
    private $name;

    /**
     * @var string Mysql集群中主节点的连接池名称
     */
    private $master;

    /**
     * @var array Mysql集群中从节点的连接池名称列表
     */
    private $slaves;

    /**
     * @var Miner SQL Builder
     */
    private $dbQueryBuilder;

    /**
     * @var mixed 详情参见PG\AOP\Wrapper::handle()
     */
    public $__wrapper;

    /**
     * @var array 指令列表
     */
    private static $asynCommand = [
        'GO', 'GOBEGIN', 'BEGIN', 'GOCOMMIT', 'COMMIT', 'GOROLLBACK', 'ROLLBACK'
    ];

    /**
     * MysqlProxyMasterSlave constructor.
     *
     * @param string $name 代理标识
     * @param array $config 配置数组
     */
    public function __construct(string $name, array $config)
    {
        $this->name   = $name;

        try {
            if (empty($config['pools'])) {
                throw new Exception('pools is empty');
            }

            if (empty($config['pools']['master'])) {
                throw new Exception('No master mysql server in master-slave config!');
            }
            $this->master = $config['pools']['master'];

            if (empty($config['pools']['slaves'])) {
                throw new Exception('No slave mysql server in master-slave config!');
            }
            $this->slaves = $config['pools']['slaves'];

            $this->startCheck();
        } catch (Exception $e) {
            writeln('Mysql Proxy ' . $e->getMessage());
        }
    }

    /**
     * 用户定时检测
     *
     * @return bool
     */
    public function check()
    {
        // 暂时未实现主从自动切换
        return true;
    }

    /**
     * 获取DB Query Builder
     *
     * @param Context $context 请求上下文对象
     * @return Miner
     */
    public function getDBQueryBuilder(Context $context = null)
    {
        if ($this->dbQueryBuilder == null) {
            $this->dbQueryBuilder            = new Miner();
        }
        $this->dbQueryBuilder->context = $context;

        return $this->dbQueryBuilder;
    }

    /**
     * 发送异步请求
     *
     * @param string $method 指令
     * @param array $arguments 指令参数
     * @return $this
     * @throws Exception
     */
    public function handle(string $method, array $arguments)
    {
        $upMethod = strtoupper($method);
        $context  = array_shift($arguments);

        if (in_array($upMethod, self::$asynCommand)) {
            $sql = '';
            if ($upMethod == 'GO') {
                if (!empty($arguments[1])) {
                    $sql = trim($arguments[1]);
                } else {
                    $sql = $this->getDBQueryBuilder($context)->getStatement(false);
                }
            }

            if (!empty($sql) && empty($arguments[0]) && !empty($this->slaves)) {
                if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0) {
                    $rand     = array_rand($this->slaves);
                    $poolName = $this->slaves[$rand];
                } else {
                    $poolName = $this->master;
                }
            } else {
                $poolName = $this->master;
            }

            $pool = getInstance()->getAsynPool($poolName);
            if ($pool == null) {
                throw new Exception("mysql pool $poolName not register!");
            }
            $this->getDBQueryBuilder($context)->mysqlPool = $pool;
            if (method_exists($this->getDBQueryBuilder(), $method)) {
                return $this->getDBQueryBuilder($context)->{$method}(...$arguments);
            } else {
                array_push($arguments, $context);
                return $this->getDBQueryBuilder($context)->mysqlPool->{$method}(...$arguments);
            }
        }

        $this->getDBQueryBuilder($context)->{$method}(...$arguments);

        return $this->__wrapper;
    }

    /**
     * 组装连接池
     *
     * @return $this
     */
    public function startCheck()
    {
        $masterPool = getInstance()->getAsynPool($this->master);
        if (!$masterPool) {
            $masterPool = new MysqlAsynPool(getInstance()->config, $this->master);
            getInstance()->addAsynPool($this->master, $masterPool, true);
        }

        foreach ($this->slaves as $slavePoolName) {
            $slavePool = getInstance()->getAsynPool($slavePoolName);
            if (!$slavePool) {
                $slavePool = new MysqlAsynPool(getInstance()->config, $slavePoolName);
                getInstance()->addAsynPool($slavePoolName, $slavePool, true);
            }
        }

        return $this;
    }
}
