<?php
/**
 * SwooleServer
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF;

use Noodlehaus\Config;
use PG\Log\PGLog;
use PG\MSF\{
    Base\Child, Controllers\ControllerFactory, Base\Loader, Base\Exception, Coroutine\GeneratorContext
};
use PG\MSF\{
    Base\Core, Pack\IPack, Route\IRoute
};
use PG\MSF\Coroutine\Scheduler as Coroutine;

abstract class Server extends Child
{
    const version = "2.0.0_alpha";
    /**
     * Daemonize.
     *
     * @var bool
     */
    public static $daemonize = false;
    /**
     * 单元测试
     * @var bool
     */
    public static $testUnity = false;
    /**
     * 单元测试文件目录
     * @var string
     */
    public static $testUnityDir = '';
    /**
     * The file to store master process PID.
     *
     * @var string
     */
    public static $pidFile = '';
    /**
     * The PID of master process.
     *
     * @var int
     */
    protected static $_masterPid = 0;
    /**
     * Log file.
     *
     * @var mixed
     */
    protected static $logFile = '';
    /**
     * Start file.
     *
     * @var string
     */
    protected static $_startFile = '';
    /**
     * worker instance.
     *
     * @var Server
     */
    protected static $_worker = null;
    /**
     * Maximum length of the show names.
     *
     * @var int
     */
    protected static $_maxShowLength = 12;
    /**
     * 协程调度器
     * @var Coroutine
     */
    public $coroutine;
    /**
     * server name
     * @var string
     */
    public $name = '';
    /**
     * server user
     * @var string
     */
    public $user = '';
    /**
     * worker数量
     * @var int
     */
    public $workerNum = 0;
    public $taskNum = 0;
    public $socketName;
    public $port;
    public $socketType;

    /**
     * 服务器到现在的毫秒数
     * @var int
     */
    public $tickTime;

    /**
     * 封包器
     * @var IPack
     */
    public $pack;
    /**
     * 路由器
     * @var IRoute
     */
    public $route;
    /**
     * 加载器
     * @var Loader
     */
    public $loader;
    /**
     * Emitted when worker processes stoped.
     *
     * @var callback
     */
    public $onErrorHandel = null;
    /**
     * @var \swoole_server
     */
    public $server;
    /**
     * @var Config
     */
    public $config;
    /**
     * 日志
     * @var Logger
     */
    public $log;
    /**
     * 是否开启tcp
     * @var bool
     */
    public $tcpEnable;

    /**
     * @var
     */
    public $packageLengthType;

    /**
     * @var int
     */
    public $packageLengthTypeLength;

    /**
     * @var
     */
    public $packageBodyOffset;

    /**
     * 协议设置
     * @var
     */
    protected $probufSet = [
        'open_length_check' => 1,
        'package_length_type' => 'N',
        'package_length_offset' => 0,       //第N个字节是包长度的值
        'package_body_offset' => 0,       //第几个字节开始计算长度
        'package_max_length' => 2000000,  //协议最大长度)
    ];

    /**
     * 是否需要协程支持(默认开启)
     * @var bool
     */
    protected $needCoroutine = true;

    /**
     * @var null
     */
    protected static $stdClass = null;

    /**
     * @var \Yac
     */
    public $sysCache;

    public function __construct()
    {
        $this->afterConstruct();
        $this->onErrorHandel = [$this, 'onErrorHandel'];
        self::$_worker = $this;
        // 加载配置 支持加载环境子目录配置
        $this->config = new Config(defined('CONFIG_DIR') ? CONFIG_DIR : [
            ROOT_PATH . '/config',
            ROOT_PATH . '/config/' . APPLICATION_ENV
        ]);
        $this->probufSet = $this->config->get('server.probuf_set', $this->probufSet);
        $this->packageLengthType = $this->probufSet['package_length_type'];
        $this->packageLengthTypeLength = strlen(pack($this->packageLengthType, 1));
        $this->packageBodyOffset = $this->probufSet['package_body_offset'];
        $this->setConfig();

        // 日志初始化
        $this->log = new PGLog($this->name);

        register_shutdown_function(array($this, 'checkErrors'));
        set_error_handler(array($this, 'displayErrorHandler'));
        //pack class
        $packClassName = "\\App\\Pack\\" . $this->config['server']['pack_tool'];
        if (class_exists($packClassName)) {
            $this->pack = new $packClassName;
        } else {
            $packClassName = "\\PG\\MSF\\Pack\\" . $this->config['server']['pack_tool'];
            if (class_exists($packClassName)) {
                $this->pack = new $packClassName;
            } else {
                throw new Exception("class {$this->config['server']['pack_tool']} is not exist.");
            }
        }
        // route class
        $routeTool = $this->config['server']['route_tool'];
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
        $this->route     = new $routeClassName;
        $this->loader    = new Loader();
    }

    /**
     * 设置配置
     * @return mixed
     */
    public function setConfig()
    {
        $this->socketType = SWOOLE_SOCK_TCP;
        $this->tcpEnable = $this->config->get('tcp.enable', false);
        $this->socketName = $this->config->get('tcp.socket', '0.0.0.0');
        $this->port = $this->config->get('tcp.port', 9501);
        $this->user = $this->config->get('server.set.user', '');

        //设置异步IO模式
        swoole_async_set([
            'thread_num' => $this->config->get('server.set.worker_num', 4),
            'aio_mode' => SWOOLE_AIO_BASE,
            'use_async_resolver' => true,
        ]);
    }

    /**
     * Run all worker instances.
     *
     * @return void
     */
    public static function run()
    {
        self::checkSapiEnv();
        self::init();
        self::parseCommand();
        self::initWorkers();
        self::displayUI();
        self::startSwooles();
    }

    /**
     * Check sapi.
     *
     * @return void
     */
    protected static function checkSapiEnv()
    {
        // Only for cli.
        if (php_sapi_name() != "cli") {
            exit("Only run in command line mode \n");
        }
    }

    /**
     * Init.
     *
     * @return void
     */
    protected static function init()
    {
        // Start file.
        $backtrace = debug_backtrace();
        self::$_startFile = $backtrace[count($backtrace) - 1]['file'];

        // Pid file.
        if (empty(self::$pidFile)) {
            self::$pidFile = self::$_worker->config->get('server.pid_path') . str_replace('/', '_',
                    self::$_startFile) . ".pid";
            if (!is_dir(self::$_worker->config->get('server.pid_path'))) {
                mkdir(self::$_worker->config->get('server.pid_path'), 0777, true);
            }
        }

        // Process title.
        self::setProcessTitle(self::$_worker->config->get('server.process_title'));
        // stdClass
        self::$stdClass = new \stdClass();
        Core::$stdClass = self::$stdClass;
    }

    /**
     * Set process name.
     *
     * @param string $title
     * @return void
     */
    public static function setProcessTitle($title)
    {
        // >=php 5.5
        if (function_exists('cli_set_process_title') && !isMac()) {
            @cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        else {
            @swoole_set_process_name($title);
        }
    }

    /**
     * Parse command.
     * php yourfile.php start | stop | reload
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
            echo("Usage: php yourfile.php {start|stop|reload|restart|test}\n");
        }

        // Get command.
        $command = trim($argv[1]);
        $command2 = isset($argv[2]) ? $argv[2] : '';

        // Start command.
        $mode = '';
        if ($command === 'start') {
            if ($command2 === '-d') {
                $mode = 'in DAEMON mode';
            } else {
                $mode = 'in DEBUG mode';
            }
        }
        echo("Swoole[$startFile] $command $mode \n");
        if (file_exists(self::$pidFile)) {
            $pids = explode(',', file_get_contents(self::$pidFile));
            // Get master process PID.
            $masterPid = $pids[0];
            $managerPid = $pids[1];
            $masterIsAlive = $masterPid && @posix_kill($masterPid, SIG_BLOCK);
        } else {
            $masterIsAlive = false;
        }
        // Master is still alive?
        if ($masterIsAlive) {
            if ($command === 'start' || $command === 'test') {
                echo("Swoole[$startFile] already running\n");
                exit;
            }
        } elseif ($command !== 'start' && $command !== 'test') {
            echo("Swoole[$startFile] not run\n");
            exit;
        }

        // execute command.
        switch ($command) {
            case 'start':
                if ($command2 === '-d') {
                    self::$daemonize = true;
                }
                break;
            case 'stop':
                @unlink(self::$pidFile);
                echo("Swoole[$startFile] is stoping ...\n");
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
                            echo("Swoole[$startFile] stop fail\n");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    echo("Swoole[$startFile] stop success\n");
                    break;
                }
                exit(0);
                break;
            case 'reload':
                posix_kill($managerPid, SIGUSR1);
                echo("Swoole[$startFile] reload\n");
                exit;
            case 'restart':
                @unlink(self::$pidFile);
                echo("Swoole[$startFile] is stoping ...\n");
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
                            echo("Swoole[$startFile] stop fail\n");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    echo("Swoole[$startFile] stop success\n");
                    break;
                }
                self::$daemonize = true;
                break;
            case 'test':
                self::$testUnity = true;
                self::$testUnityDir = $command2;
                break;
            default :
                exit("Usage: php yourfile.php {start|stop|reload|restart|test}\n");
        }
    }

    /**
     * Init All worker instances.
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
                echo('Warning: You must have the root privileges to change uid and gid.');
            }
        }
    }

    /**
     * Get unix user of current porcess.
     *
     * @return string
     */
    protected static function getCurrentUser()
    {
        $userInfo = posix_getpwuid(posix_getuid());
        return $userInfo['name'];
    }

    /**
     * Display staring UI.
     *
     * @return void
     */
    protected static function displayUI()
    {
        $setConfig = self::$_worker->setServerSet();
        echo "\033[2J";
        echo "\033[1A\n\033[K-------------\033[47;30m MSF \033[0m--------------\n\033[0m";
        echo 'System:', PHP_OS, "\n";
        echo 'MSF version:', self::version, "\n";
        echo 'Swoole version: ', SWOOLE_VERSION, "\n";
        echo 'PHP version: ', PHP_VERSION, "\n";
        echo 'worker_num: ', $setConfig['worker_num'], "\n";
        echo 'task_num: ', $setConfig['task_worker_num']??0, "\n";
        echo "-------------------\033[47;30m" . self::$_worker->name . "\033[0m----------------------\n";
        echo "\033[47;30mtype\033[0m", str_pad('',
            self::$_maxShowLength - strlen('type')), "\033[47;30msocket\033[0m", str_pad('',
            self::$_maxShowLength - strlen('socket')), "\033[47;30mport\033[0m", str_pad('',
            self::$_maxShowLength - strlen('port')), "\033[47;30m", "status\033[0m\n";
        switch (self::$_worker->name) {
            case MSFServer::SERVER_NAME:
                echo str_pad('TCP',
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('tcp.socket', '--'),
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('tcp.port', '--'),
                    self::$_maxShowLength - 2);
                if (self::$_worker->tcpEnable??false) {
                    echo " \033[32;40m [OPEN] \033[0m\n";
                } else {
                    echo " \033[31;40m [CLOSE] \033[0m\n";
                }
                echo str_pad('HTTP',
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('http_server.socket', '--'),
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('http_server.port', '--'),
                    self::$_maxShowLength - 2);
                if (self::$_worker->httpEnable??false) {
                    echo " \033[32;40m [OPEN] \033[0m\n";
                } else {
                    echo " \033[31;40m [CLOSE] \033[0m\n";
                }
                echo str_pad('WEBSOCKET',
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('http_server.socket', '--'),
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('http_server.port', '--'),
                    self::$_maxShowLength - 2);
                if (self::$_worker->websocketEnable??false) {
                    echo " \033[32;40m [OPEN] \033[0m\n";
                } else {
                    echo " \033[31;40m [CLOSE] \033[0m\n";
                }
                echo str_pad('DISPATCH',
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('tcp.socket', '--'),
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('server.dispatch_port', '--'),
                    self::$_maxShowLength - 2);
                if (self::$_worker->config->get('use_dispatch', false)) {
                    echo " \033[32;40m [OPEN] \033[0m\n";
                } else {
                    echo " \033[31;40m [CLOSE] \033[0m\n";
                }
                break;
        }
        echo "-----------------------------------------------\n";
        if (self::$daemonize) {
            global $argv;
            $startFile = $argv[0];
            echo "Input \"php $startFile stop\" to quit. Start success.\n";
        } else {
            echo "Press Ctrl-C to quit. Start success.\n";
        }
    }

    /**
     * 设置服务器配置参数
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
     * 启动
     */
    public function start()
    {
        if ($this->tcpEnable) {
            $this->server = new \swoole_server($this->socketName, $this->port, SWOOLE_PROCESS, $this->socketType);
            $this->server->on('Start', [$this, 'onSwooleStart']);
            $this->server->on('WorkerStart', [$this, 'onSwooleWorkerStart']);
            $this->server->on('connect', [$this, 'onSwooleConnect']);
            $this->server->on('receive', [$this, 'onSwooleReceive']);
            $this->server->on('close', [$this, 'onSwooleClose']);
            $this->server->on('WorkerStop', [$this, 'onSwooleWorkerStop']);
            $this->server->on('Task', [$this, 'onSwooleTask']);
            $this->server->on('Finish', [$this, 'onSwooleFinish']);
            $this->server->on('PipeMessage', [$this, 'onSwoolePipeMessage']);
            $this->server->on('WorkerError', [$this, 'onSwooleWorkerError']);
            $this->server->on('ManagerStart', [$this, 'onSwooleManagerStart']);
            $this->server->on('ManagerStop', [$this, 'onSwooleManagerStop']);
            $this->server->on('Packet', [$this, 'onSwoolePacket']);
            $set = $this->setServerSet();
            $set['daemonize'] = self::$daemonize ? 1 : 0;
            $this->server->set($set);
            $this->beforeSwooleStart();
            $this->server->start();
        } else {
            print_r("没有任何服务启动\n");
            exit(0);
        }
    }

    /**
     * start前的操作
     */
    public function beforeSwooleStart()
    {

    }

    /**
     * onSwooleStart
     * @param $serv
     */
    public function onSwooleStart($serv)
    {
        self::$_masterPid = $serv->master_pid;
        file_put_contents(self::$pidFile, self::$_masterPid);
        file_put_contents(self::$pidFile, ',' . $serv->manager_pid, FILE_APPEND);
        self::setProcessTitle($this->config['server.process_title'] . '-Master');
    }

    /**
     * onSwooleWorkerStart
     * @param $serv
     * @param $workerId
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        //清除apc缓存
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
        file_put_contents(self::$pidFile, ',' . $serv->worker_pid, FILE_APPEND);
        if (!$serv->taskworker) {//worker进程
            if ($this->needCoroutine) {//启动协程调度器
                $this->coroutine = new Coroutine();
            }
            self::setProcessTitle($this->config['server.process_title'] . '-Worker');
        } else {
            self::setProcessTitle($this->config['server.process_title'] . '-Tasker');
        }
    }

    /**
     * onSwooleConnect
     * @param $serv
     * @param $fd
     */
    public function onSwooleConnect($serv, $fd)
    {
    }

    /**
     * 客户端有消息时
     * @param $serv
     * @param $fd
     * @param $fromId
     * @param $data
     * @return Controllers\Controller|void
     */
    public function onSwooleReceive($serv, $fd, $fromId, $data)
    {
        $error = '';
        $code = 500;
        $data = substr($data, $this->packageLengthTypeLength);
        //反序列化，出现异常断开连接
        try {
            $clientData = $this->pack->unPack($data);
        } catch (\Exception $e) {
            $serv->close($fd);
            return null;
        }
        //client_data进行处理
        $clientData = $this->route->handleClientData($clientData);
        $controllerName = $this->route->getControllerName();
        $controllerInstance = ControllerFactory::getInstance()->getController($controllerName);

        if ($controllerInstance == null) {
            $controllerName = $this->route->getControllerName() . "\\" . $this->route->getMethodName();
            $controllerInstance = ControllerFactory::getInstance()->getController($controllerName);
            $this->route->setControllerName($controllerName);
        }

        if ($controllerInstance != null) {
            if (Server::$testUnity) {
                $fd = 'self';
                $uid = $fd;
            } else {
                $uid = $serv->connection_info($fd)['uid']??0;
            }
            $methodName = $this->config->get('tcp.method_prefix', '') . $this->route->getMethodName();
            $controllerInstance->setClientData($uid, $fd, $clientData, $controllerName, $methodName);

            if (!method_exists($controllerInstance, $methodName)) {
                $methodName = $this->config->get('tcp.method_prefix', '') . $this->config->get('tcp.default_method',
                        'Index');
                $this->route->setMethodName($this->config->get('tcp.default_method', 'Index'));
            }

            if (!method_exists($controllerInstance, $methodName)) {
                $error = 'Api not found method(' . $methodName . ')';
                $code = 404;
            } else {
                try {
                    $generator = call_user_func([$controllerInstance, $methodName], $this->route->getParams());
                    if ($generator instanceof \Generator) {
                        $generatorContext = new GeneratorContext();
                        $generatorContext->setController($controllerInstance, $controllerName, $methodName);
                        $this->coroutine->start($generator, $generatorContext);
                    }
                } catch (\Throwable $e) {
                    call_user_func([$controllerInstance, 'onExceptionHandle'], $e);
                }
            }
        } else {
            $error = 'Api not found controller(' . $controllerName . ')';
            $code = 404;
        }

        if ($error !== '') {
            if ($controllerInstance != null) {
                $controllerInstance->destroy();
            }

            $response = json_encode([
                'data' => self::$stdClass,
                'message' => $error,
                'status' => $code,
                'serverTime' => microtime(true)
            ]);
            $response = getInstance()->encode($this->pack->pack($response));
            getInstance()->send($fd, $response);
        }
    }

    /**
     * 数据包编码
     * @param $buffer
     * @return string
     */
    public function encode($buffer)
    {
        $totalLength = $this->packageLengthTypeLength + strlen($buffer) - $this->packageBodyOffset;
        return pack($this->packageLengthType, $totalLength) . $buffer;
    }

    /**
     * onSwooleClose
     * @param $serv
     * @param $fd
     */
    public function onSwooleClose($serv, $fd)
    {

    }

    /**
     * onSwooleWorkerStop
     * @param $serv
     * @param $fd
     */
    public function onSwooleWorkerStop($serv, $fd)
    {

    }

    /**
     * onSwooleTask
     * @param $serv
     * @param $taskId
     * @param $fromId
     * @param $data
     * @return mixed
     */
    public function onSwooleTask($serv, $taskId, $fromId, $data)
    {

    }

    /**
     * onSwooleFinish
     * @param $serv
     * @param $taskId
     * @param $data
     */
    public function onSwooleFinish($serv, $taskId, $data)
    {

    }

    /**
     * onSwoolePipeMessage
     * @param $serv
     * @param $fromWorkerId
     * @param $message
     */
    public function onSwoolePipeMessage($serv, $fromWorkerId, $message)
    {

    }

    /**
     * onSwooleWorkerError
     * @param $serv
     * @param $workerId
     * @param $workerPid
     * @param $exitCode
     */
    public function onSwooleWorkerError($serv, $workerId, $workerPid, $exitCode)
    {
        $data = [
            'worker_id' => $workerId,
            'worker_pid' => $workerPid,
            'exit_code' => $exitCode
        ];
        $log = "WORKER Error ";
        $log .= json_encode($data);
        $this->log->error($log);
        if ($this->onErrorHandel != null) {
            call_user_func($this->onErrorHandel, '【！！！】服务器进程异常退出', $log);
        }
    }

    /**
     * ManagerStart
     * @param $serv
     */
    public function onSwooleManagerStart($serv)
    {
        self::setProcessTitle($this->config['server.process_title'] . '-Manager');
    }

    /**
     * ManagerStop
     * @param $serv
     */
    public function onSwooleManagerStop($serv)
    {

    }

    /**
     * onPacket(UDP)
     * @param $server
     * @param string $data
     * @param array $clientInfo
     */
    public function onSwoolePacket($server, $data, $clientInfo)
    {

    }

    /**
     * 包装SerevrMessageBody消息
     * @param $type
     * @param $message
     * @return string
     */
    public function packSerevrMessageBody($type, $message)
    {
        $data['type'] = $type;
        $data['message'] = $message;
        return serialize($data);
    }

    /**
     * 魔术方法
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->server, $name), $arguments);
    }

    /**
     * 全局错误监听
     * @param $error
     * @param $errorString
     * @param $filename
     * @param $line
     * @param $symbols
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
        if ($this->onErrorHandel != null) {
            call_user_func($this->onErrorHandel, '服务器发生严重错误', $log);
        }
    }

    /**
     * Check errors when current process exited.
     *
     * @return void
     */
    public function checkErrors()
    {
        $log = "WORKER EXIT UNEXPECTED ";
        $error = error_get_last();
        if (isset($error['type'])) {
            switch ($error['type']) {
                case E_ERROR :
                case E_PARSE :
                case E_CORE_ERROR :
                case E_COMPILE_ERROR :
                    $message = $error['message'];
                    $file = $error['file'];
                    $line = $error['line'];
                    $log .= "$message ($file:$line)\nStack trace:\n";
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
                        $log .= "#$i {$t['file']}({$t['line']}): ";
                        if (isset($t['object']) and is_object($t['object'])) {
                            $log .= get_class($t['object']) . '->';
                        }
                        $log .= "{$t['function']}()\n";
                    }
                    if (isset($_SERVER['REQUEST_URI'])) {
                        $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
                    }
                    $this->log->alert($log);
                    if ($this->onErrorHandel != null) {
                        call_user_func($this->onErrorHandel, '服务器发生崩溃事件', $log);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Get socket name.
     *
     * @return string
     */
    public function getSocketName()
    {
        return $this->socketName ? lcfirst($this->socketName . ":" . $this->port) : 'none';
    }

    /**
     * 判断这个fd是不是一个WebSocket连接，用于区分tcp和websocket
     * 握手后才识别为websocket
     * @param $fd
     * @return bool
     * @throws \Exception
     */
    public function isWebSocket($fd)
    {
        $fdinfo = $this->server->connection_info($fd);
        if (empty($fdinfo)) {
            throw new \Exception('fd not exist');
        }
        if (key_exists('websocket_status', $fdinfo) && $fdinfo['websocket_status'] == WEBSOCKET_STATUS_FRAME) {
            return true;
        }
        return false;
    }

    /**
     * 是否是task进程
     * @return bool
     */
    public function isTaskWorker()
    {
        return $this->server->taskworker;
    }

    /**
     * 判断是tcp还是websocket进行发送
     * @param $fd
     * @param $data
     */
    public function send($fd, $data)
    {
        $this->server->send($fd, $data);
    }

    /**
     * 服务器主动关闭链接
     * close fd
     * @param $fd
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
    public function onErrorHandel($msg, $log)
    {
        print_r($msg . "\n");
        print_r($log . "\n");
    }

}
