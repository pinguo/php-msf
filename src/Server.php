<?php
/**
 * SwooleServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use PG\MSF\Base\Exception;
use Noodlehaus\Config;
use PG\Log\PGLog;
use PG\MSF\Base\Child;
use PG\MSF\Base\Core;
use PG\MSF\Pack\IPack;
use PG\MSF\Route\IRoute;
use PG\MSF\Helpers\Context;
use PG\MSF\Coroutine\Scheduler;
use PG\MSF\Base\AOPFactory;
use PG\MSF\Base\Pool;
use PG\MSF\Route\NormalRoute;

/**
 * Class Server
 * @package PG\MSF
 */
abstract class Server extends Child
{
    /**
     * 版本
     */
    const version = "3.0.5";

    /**
     * 运行方式（web/console）
     */
    const mode = 'web';

    /**
     * @var int 进程类型
     */
    public $processType = Macro::PROCESS_MASTER;

    /**
     * @var Server 实例
     */
    protected static $instance;

    /**
     * @var bool Daemonize.
     */
    public static $daemonize = false;

    /**
     * @var string pid文件
     */
    public static $pidFile = '';

    /**
     * @var int Master进程ID
     */
    protected static $_masterPid = 0;

    /**
     * @var mixed 日志文件
     */
    protected static $logFile = '';

    /**
     * @var string Start file.
     */
    protected static $_startFile = '';

    /**
     * @var Server worker instance.
     */
    protected static $_worker = null;

    /**
     * @var Scheduler 协程调度器
     */
    public $scheduler;

    /**
     * @var string server name
     */
    public $name = '';

    /**
     * @var string server user
     */
    public $user = '';

    /**
     * @var int Worker数量
     */
    public $workerNum = 0;

    /**
     * @var int Tasker数量
     */
    public $taskNum = 0;

    /**
     * @var int 服务器到现在的毫秒数
     */
    public $tickTime;

    /**
     * @var IRoute|NormalRoute 路由器
     */
    protected $route;

    /**
     * @var IPack 数据打包与解包
     */
    public $pack;

    /**
     * @var callback 错误回调
     */
    public $onErrorHandle = null;

    /**
     * @var \swoole_server Server运行实例
     */
    public $server;

    /**
     * @var Config 配置对象
     */
    public $config;

    /**
     * @var PGLog 日志对象
     */
    public $log;

    /**
     * @var \stdClass|null 对象模板
     */
    protected static $stdClass = null;

    /**
     * @var \Yac 系统共享对象操作句柄
     */
    public $sysCache;

    /**
     * 系统注册的定时器列表
     *
     * @var array
     */
    public $sysTimers;

    /**
     * @var string 框架目录
     */
    public $MSFSrcDir;

    /**
     * @var Pool 对象池
     */
    public $objectPool;

    /**
     * var array 进程内对象容器
     */
    public $objectPoolBuckets = [];

    /**
     * @var int 请求ID
     */
    public $requestId = 0;

    /**
     * Server constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->MSFSrcDir = __DIR__;
        $this->onErrorHandle = [$this, 'onErrorHandle'];
        self::$_worker = $this;
        // 加载配置 支持加载环境子目录配置
        $this->config = new Config(defined('CONFIG_DIR') ? CONFIG_DIR : [
            ROOT_PATH . '/config',
            ROOT_PATH . '/config/' . APPLICATION_ENV
        ]);
        $this->setConfig();

        // 日志初始化
        $this->log = new PGLog($this->name, $this->config->get('server.log', ['handlers' => []]));

        register_shutdown_function(array($this, 'checkErrors'));
        set_error_handler(array($this, 'displayErrorHandler'));

        // 初始化路由器
        $routeTool = $this->config->get('server.route_tool', 'NormalRoute');
        if (class_exists($routeTool)) {
            $routeClassName = $routeTool;
        } else {
            $routeClassName = "\\App\\Route\\" . $routeTool;
            if (!class_exists($routeClassName)) {
                $routeClassName = "\\PG\\MSF\\Route\\" . $routeTool;
                if (!class_exists($routeClassName)) {
                    throw new Exception("class {$routeTool} is not exist.");
                }
            }
        }
        $this->route  = new $routeClassName;

        // 初始化打包和解包对象
        $packTool = $this->config->get('server.pack_tool', 'JsonPack');
        if (class_exists($packTool)) {
            $packClassName = $packTool;
        } else {
            $packClassName = "\\App\\Pack\\" . $packTool;
            if (!class_exists($packClassName)) {
                $packClassName = "\\PG\\MSF\\Pack\\" . $packTool;
                if (!class_exists($packClassName)) {
                    throw new Exception("class {$packTool} is not exist.");
                }
            }
        }
        $this->pack = new $packClassName;
    }

    /**
     * 获取运行的Server实例
     *
     * @return Server|MSFServer
     */
    public static function &getInstance()
    {
        return self::$instance;
    }

    /**
     * 配置初始化
     *
     * @return mixed
     */
    public function setConfig()
    {
        $this->user = $this->config->get('server.set.user', '');

        //设置异步IO模式
        swoole_async_set($this->config->get('server.async_io', [
            'thread_num'         => $this->config->get('server.set.worker_num', 4),
            'aio_mode'           => SWOOLE_AIO_BASE,
            'use_async_resolver' => true,
            'dns_lookup_random'  => true,
        ]));
    }

    /**
     * 运行所有的服务进程
     *
     * @return void
     */
    public static function run()
    {
        static::init();
        static::parseCommand();
        static::initWorkers();
        static::displayUI();
        static::startSwooles();
    }

    /**
     * 服务初始化
     *
     * @return void
     */
    protected static function init()
    {
        $backtrace        = debug_backtrace();
        self::$_startFile = $backtrace[count($backtrace) - 1]['file'];

        if (empty(self::$pidFile)) {
            self::$pidFile = self::$_worker->config->get('server.pid_path') . str_replace('/', '_', self::$_startFile) . ".pid";
            if (!is_dir(self::$_worker->config->get('server.pid_path'))) {
                mkdir(self::$_worker->config->get('server.pid_path'), 0777, true);
            }
        }

        self::setProcessTitle(self::$_worker->config->get('server.process_title'));
        self::$stdClass = new \stdClass();
        Core::$stdClass = self::$stdClass;
    }

    /**
     * 设置服务进程名称
     *
     * @param string $title
     * @return void
     */
    public static function setProcessTitle($title)
    {
        if (function_exists('cli_set_process_title') && PHP_OS == 'Darwin') {
            @cli_set_process_title($title);
        } else {
            @swoole_set_process_name($title);
        }
    }

    /**
     * 停止当前worker.
     *
     * @param        $masterPid
     * @param string $startFile
     */
    protected static function stopWorker($masterPid, $startFile = '')
    {
        @unlink(self::$pidFile);
        writeln("$startFile is stoping ...");
        // Send stop signal to master process.
        $masterPid && posix_kill($masterPid, SIGTERM);
        // Timeout.
        $timeout = 5;
        $startTime = time();
        // Check master process is still alive?
        while (1) {
            $masterIsAlive = $masterPid && posix_kill($masterPid, SIG_BLOCK);
            if ($masterIsAlive) {
                // Timeout?
                if (time() - $startTime >= $timeout) {
                    writeln("{$startFile} stop fail");
                    exit;
                }
                // Waiting amoment.
                usleep(10000);
                continue;
            }
            // Stop success.
            writeln("{$startFile} stop success");
            break;
        }
    }


    /**
     * 获取当前服务器的pid数据.包含俩个key:
     * [
     *      'masterPid' => 主进程pid.
     *      'managerPid' => manager进程pid.
     * ]
     *
     * @return array|bool 如果master活着则返回pid信息,否则返回false.
     */
    protected static function getServerPidInfo()
    {
        $masterPid = $managerPid = null;
        if (file_exists(self::$pidFile)) {
            $pids = explode(',', file_get_contents(self::$pidFile));
            // Get master process PID.
            $masterPid = $pids[0];
            $managerPid = $pids[1];
            $masterIsAlive = $masterPid && @posix_kill($masterPid, SIG_BLOCK);
        } else {
            $masterIsAlive = false;
        }
        return $masterIsAlive ? [
            'masterPid' => $masterPid,
            'managerPid' => $managerPid,
        ] : false;
    }

    /**
     * 解析命令行参数
     *
     * @return void
     */
    protected static function parseCommand()
    {
        global $argv;
        // Check argv;
        $startFile = $argv[0];
        if (!isset($argv[1])) {
            $argv[1] = 'start';
        }

        // Get command.
        $command = trim($argv[1]);
        $command2 = isset($argv[2]) ? $argv[2] : '';

        $pidInfo = static::getServerPidInfo();

        // Master is still alive?
        if ($pidInfo !== false) {
            if ($command === 'start' || $command === 'test') {
                writeln("{$startFile} already running");
                exit;
            }
        } elseif ($command !== 'start' && $command !== 'test') {
            writeln("{$startFile} not run");
            exit;
        }

        $masterPid = $pidInfo['masterPid'];
        $managerPid = $pidInfo['managerPid'];

        // execute command.
        switch ($command) {
            case 'start':
                if ($command2 === '-d') {
                    self::$daemonize = true;
                }
                break;
            case 'stop':
                self::stopWorker($masterPid, $startFile);
                exit(0);
                break;
            case 'reload':
                posix_kill($managerPid, SIGUSR1);
                writeln("{$startFile} reload");
                exit;
            case 'restart':
                self::stopWorker($masterPid, $startFile);
                self::$daemonize = true;
                break;
            case 'test':
                self::$testUnity = true;
                self::$testUnityDir = $command2;
                break;
            default:
        }
    }

    /**
     * 初始化worker进程
     *
     * @return void
     */
    protected static function initWorkers()
    {
        // Worker name.
        if (empty(self::$_worker->name)) {
            self::$_worker->name = 'none';
        }
        // Get unix user of the worker process.
        if (empty(self::$_worker->user)) {
            self::$_worker->user = self::getCurrentUser();
        } else {
            if (posix_getuid() !== 0 && self::$_worker->user != self::getCurrentUser()) {
                writeln('You must have the root privileges to change uid and gid.');
            }
        }
    }

    /**
     * 获取当前进程用户
     *
     * @return string
     */
    protected static function getCurrentUser()
    {
        $userInfo = posix_getpwuid(posix_getuid());
        return $userInfo['name'];
    }

    /**
     * 显示命令行控制台信息
     *
     * @return void
     */
    protected static function displayUI()
    {
        global $argv;
        $setConfig = self::$_worker->setServerSet();
        $ascii     = file_get_contents(__DIR__ . '/../ascii.ui');
        writeln("Start   Command: " . implode(" ", $argv) . "\n "  . $ascii);
        writeln('MSF     Version: ' . self::version);
        writeln('Swoole  Version: ' . SWOOLE_VERSION);
        writeln('PHP     Version: ' . PHP_VERSION);
        writeln('Application ENV: ' . APPLICATION_ENV);
        writeln('Worker   Number: ' . ($setConfig['worker_num'] ?? 0));
        writeln('Task     Number: ' . ($setConfig['task_worker_num'] ?? 0));
        writeln("Listen     Addr: " . self::$_worker->config->get('http_server.socket'));
        writeln("Listen     Port: " . self::$_worker->config->get('http_server.port'));
    }

    /**
     * 设置服务器配置参数
     *
     * @return mixed
     */
    abstract public function setServerSet();

    /**
     * Fork some worker processes.
     *
     * @return void
     */
    protected static function startSwooles()
    {
        self::$_worker->start();
    }

    /**
     * start前的操作
     */
    public function beforeSwooleStart()
    {
        //初始化对象池
        $this->objectPool = Pool::getInstance();
    }

    /**
     * Server启动在主进程的主线程回调此函数
     *
     * @param $serv
     */
    public function onStart($serv)
    {
        self::$_masterPid = $serv->master_pid;
        $this->processType = Macro::PROCESS_MASTER;
        file_put_contents(self::$pidFile, self::$_masterPid);
        file_put_contents(self::$pidFile, ',' . $serv->manager_pid, FILE_APPEND);
        self::setProcessTitle($this->config['server.process_title'] . '-' . Macro::PROCESS_NAME[$this->processType]);
    }

    /**
     * worker进程/task进程启动回调
     *
     * @param \swoole_server $serv server实例
     * @param int $workerId worker id
     */
    public function onWorkerStart($serv, $workerId)
    {
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        file_put_contents(self::$pidFile, ',' . getmypid(), FILE_APPEND);
        if ($this->processType != Macro::PROCESS_TASKER) {
            $this->scheduler = new Scheduler();
        }

        self::setProcessTitle($this->config['server.process_title'] . '-' . Macro::PROCESS_NAME[$this->processType]);
    }

    /**
     * TCP客户端连接关闭后，在worker进程中回调此函数
     *
     * @param $serv
     * @param $fd
     */
    public function onClose($serv, $fd)
    {
    }

    /**
     * 此事件在worker进程终止时发生
     *
     * @param $serv
     * @param $fd
     */
    public function onWorkerStop($serv, $fd)
    {
    }

    /**
     * 在task_worker进程内被调用
     *
     * @param $serv
     * @param $taskId
     * @param $fromId
     * @param $data
     * @return mixed
     */
    public function onTask($serv, $taskId, $fromId, $data)
    {
    }

    /**
     * 当worker进程投递的任务在task_worker中完成时调用
     *
     * @param $serv
     * @param $taskId
     * @param $data
     */
    public function onFinish($serv, $taskId, $data)
    {
    }

    /**
     * 当工作进程收到由sendMessage发送的管道消息时会触发onPipeMessage事件
     *
     * @param $serv
     * @param $fromWorkerId
     * @param $message
     */
    public function onPipeMessage($serv, $fromWorkerId, $message)
    {
    }

    /**
     * 当worker/task_worker进程发生异常后会在Manager进程内回调此函数。
     *
     * @param \swoole_server $serv server实例
     * @param int $workerId worker id
     * @param int $workerPid worker pid
     * @param int $exitCode 退出状态码
     */
    public function onWorkerError($serv, $workerId, $workerPid, $exitCode)
    {
        $data = [
            'worker_id'  => $workerId,
            'worker_pid' => $workerPid,
            'exit_code'  => $exitCode,
            'message'    => error_get_last(),
        ];
        $log = "WORKER Error ";
        $log .= json_encode($data);
        $this->log->error($log);
        if ($this->onErrorHandle != null) {
            $this->onErrorHandle('服务器进程异常退出', $log);
        }
    }

    /**
     * 当管理进程启动时调用
     *
     * @param $serv
     */
    public function onManagerStart($serv)
    {
        $this->processType = Macro::PROCESS_MANAGER;
        self::setProcessTitle($this->config['server.process_title'] . '-' . Macro::PROCESS_NAME[$this->processType]);
    }

    /**
     * 当管理进程结束时调
     *
     * @param $serv
     */
    public function onManagerStop($serv)
    {
    }

    /**
     * 关闭服务
     *
     * @param $serv
     */
    public function onShutdown($serv)
    {
    }

    /**
     * __call魔术方法
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->server->$name(...$arguments);
    }

    /**
     * 添加定时器
     * 定时器包装（用于业务Timer进程）
     *
     * @param int $ms 定时器间隔毫秒
     * @param callable $callBack 定时器执行的回调
     * @param array $params 定时器其他参数
     * @param string|callable $tickType 定时器类型，可选Macro::SWOOLE_TIMER_TICK，Macro::SWOOLE_TIMER_AFTER
     * @throws Exception
     */
    public function addTimer($ms, callable $callBack, $params = [], $tickType = Macro::SWOOLE_TIMER_TICK)
    {
        if (!in_array($tickType, ['swoole_timer_tick', 'swoole_timer_after'])) {
            throw new Exception("not support $tickType tick type");
        }

        $tickType($ms, function ($timerId) use ($callBack, $params) {
            $instance = false;
            try {
                $this->requestId++;
                $methodName = $params['name'] ?? 'TimerTick';
                $controllerName = 'TimerTick';
                // 构造请求日志对象
                $PGLog = clone getInstance()->log;
                $PGLog->accessRecord['beginTime'] = microtime(true);
                $PGLog->accessRecord['uri'] = '/' . $controllerName . '/' . $methodName;
                $PGLog->logId = $this->genLogId(null);;
                defined('SYSTEM_NAME') && $PGLog->channel = SYSTEM_NAME;
                $PGLog->init();
                $PGLog->pushLog('controller', $controllerName);
                $PGLog->pushLog('method', $methodName);

                /**
                 * @var \PG\MSF\Controllers\Controller $instance
                 */
                $instance = $this->objectPool->get(\PG\MSF\Controllers\Controller::class,
                    [$controllerName, $methodName]);
                $instance->__useCount++;
                if (empty($instance->getObjectPool())) {
                    $instance->setObjectPool(AOPFactory::getObjectPool(getInstance()->objectPool, $instance));
                }

                $instance->context = $instance->getObjectPool()->get(Context::class, [$this->requestId]);
                // 初始化控制器
                $instance->requestStartTime = microtime(true);
                $instance->context->setLogId($PGLog->logId);
                $instance->context->setLog($PGLog);
                $instance->context->setObjectPool($instance->getObjectPool());
                $instance->context->setControllerName($controllerName);
                $instance->context->setActionName($methodName);
                $instance->__construct($controllerName, $methodName);

                // 执行定时请求
                $run = $callBack($instance, $timerId, $params);
                if ($run instanceof \Generator) {
                    $this->scheduler->start($run, $instance, function () use ($instance) {
                        $instance->destroy();
                    });
                } else {
                    $instance->destroy();
                }
            } catch (\Throwable $e) {
                if ($instance) {
                    $instance->destroy();
                }
            }
        }, $params);
    }

    /**
     * 全局错误监听
     *
     * @param int $error 错误码
     * @param string $errorString 错误描述
     * @param string $filename 文件名
     * @param int $line 文件行
     * @param array $symbols 符号表
     */
    public function displayErrorHandler($error, $errorString, $filename, $line, $symbols)
    {
        // 如果表达式前面有@时忽略错误
        if (0 == error_reporting()) {
            return;
        }

        $log = "WORKER Error ";
        $log .= "$errorString ($filename:$line)";
        $this->log->error($log);
        if ($this->onErrorHandle != null) {
            $this->onErrorHandle('服务器发生严重错误', $log);
        }
    }

    /**
     * 致命错误回调
     *
     * @return void
     */
    public function checkErrors()
    {
        if ($this->processType == Macro::PROCESS_WORKER) {
            // exit统计
            $key = Macro::SERVER_STATS . getInstance()->server->worker_id . '_exit';
            $exitStat = $this->sysCache->get($key);
            if (!$exitStat) {
                $exitStat = 1;
            } else {
                $exitStat++;
            }
            $this->sysCache->set($key, $exitStat);
        }

        // 错误信息
        $logMsg  = "WORKER EXIT UNEXPECTED ";
        $error   = error_get_last();

        if (isset($error['type'])) {
            switch ($error['type']) {
                case E_ERROR:
                case E_PARSE:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                    $message = $error['message'];
                    $file = $error['file'];
                    $line = $error['line'];
                    $logMsg .= "$message ($file:$line)\nStack trace:\n";
                    $trace = debug_backtrace();
                    foreach ($trace as $i => $t) {
                        if (!isset($t['file'])) {
                            $t['file'] = 'unknown';
                        }
                        if (!isset($t['line'])) {
                            $t['line'] = 0;
                        }
                        if (!isset($t['function'])) {
                            $t['function'] = 'unknown';
                        }
                        $logMsg .= "#$i {$t['file']}({$t['line']}): ";
                        if (isset($t['object']) and is_object($t['object'])) {
                            $logMsg .= get_class($t['object']) . '->';
                        }
                        $logMsg .= "{$t['function']}()\n";
                    }
                    if (isset($_SERVER['REQUEST_URI'])) {
                        $logMsg .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
                    }
                    $this->log->alert($logMsg);
                    if ($this->onErrorHandle != null) {
                        $this->onErrorHandle('服务器发生崩溃', $logMsg);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * 是否是task进程
     * @return bool
     */
    public function isTaskWorker()
    {
        return !empty($this->server) && property_exists($this->server, 'taskworker') && $this->server->taskworker;
    }

    /**
     * 服务器主动关闭链接
     *
     * @param int $fd
     */
    public function close($fd)
    {
        $this->server->close($fd);
    }

    /**
     * 错误处理函数
     * @param $msg
     * @param $log
     */
    public function onErrorHandle($msg, $log)
    {
        writeln($msg . ' ' . $log);
    }
}
