<?php
namespace Server;

use app\Helpers\PGLog;
use Noodlehaus\Config;
use Server\CoreBase\Child;
use Server\CoreBase\ControllerFactory;
use Server\CoreBase\Coroutine;
use Server\CoreBase\GeneratorContext;
use Server\CoreBase\Loader;
use Server\CoreBase\SwooleException;
use Server\Pack\IPack;
use Server\Route\IRoute;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-28
 * Time: 上午11:37
 */
abstract class SwooleServer extends Child
{
    const version = "1.7.5";
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
     * @var SwooleServer
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
    public $worker_num = 0;
    public $task_num = 0;
    public $socket_name;
    public $port;
    public $socket_type;

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
    public $tcp_enable;

    /**
     * @var
     */
    public $package_length_type;

    /**
     * @var int
     */
    public $package_length_type_length;

    /**
     * @var
     */
    public $package_body_offset;

    /**
     * 协议设置
     * @var
     */
    protected $probuf_set = ['open_length_check' => 1,
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

    public function __construct()
    {
        $this->onErrorHandel = [$this, 'onErrorHandel'];
        self::$_worker = $this;
        // 加载配置 支持加载环境子目录配置
        $this->config = new Config([__DIR__ . '/../config', __DIR__ . '/../config/' . ENV]);
        $this->probuf_set = $this->config->get('server.probuf_set', $this->probuf_set);
        $this->package_length_type = $this->probuf_set['package_length_type'];
        $this->package_length_type_length = strlen(pack($this->package_length_type, 1));
        $this->package_body_offset = $this->probuf_set['package_body_offset'];
        $this->setConfig();

        // 日志初始化
        $this->log = new PGLog($this->name);

        register_shutdown_function(array($this, 'checkErrors'));
        set_error_handler(array($this, 'displayErrorHandler'));
        //pack class
        $pack_class_name = "\\app\\Pack\\" . $this->config['server']['pack_tool'];
        if (class_exists($pack_class_name)) {
            $this->pack = new $pack_class_name;
        } else {
            $pack_class_name = "\\Server\\Pack\\" . $this->config['server']['pack_tool'];
            if (class_exists($pack_class_name)) {
                $this->pack = new $pack_class_name;
            } else {
                throw new SwooleException("class {$this->config['server']['pack_tool']} is not exist.");
            }
        }
        //route class
        $route_class_name = "\\app\\Route\\" . $this->config['server']['route_tool'];
        if (class_exists($route_class_name)) {
            $this->route = new $route_class_name;
        } else {
            $route_class_name = "\\Server\\Route\\" . $this->config['server']['route_tool'];
            if (class_exists($route_class_name)) {
                $this->route = new $route_class_name;
            } else {
                throw new SwooleException("class {$this->config['server']['route_tool']} is not exist.");
            }
        }
        $this->loader = new Loader();
    }

    /**
     * 设置配置
     * @return mixed
     */
    public function setConfig()
    {
        $this->socket_type = SWOOLE_SOCK_TCP;
        $this->tcp_enable = $this->config->get('tcp.enable', true);
        $this->socket_name = $this->config['tcp']['socket'];
        $this->port = $this->config['tcp']['port'];
        $this->user = $this->config->get('server.set.user', '');
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
            exit("only run in command line mode \n");
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
            self::$pidFile = self::$_worker->config->get('server.pid_path') . str_replace('/', '_', self::$_startFile) . ".pid";
        }

        // Process title.
        self::setProcessTitle(self::$_worker->config->get('server.process_title'));
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
        $start_file = $argv[0];
        if (!isset($argv[1])) {
            exit("Usage: php yourfile.php {start|stop|reload|restart|test}\n");
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
        echo("Swoole[$start_file] $command $mode \n");
        if (file_exists(self::$pidFile)) {
            $pids = explode(',', file_get_contents(self::$pidFile));
            // Get master process PID.
            $master_pid = $pids[0];
            $manager_pid = $pids[1];
            $master_is_alive = $master_pid && @posix_kill($master_pid, SIG_BLOCK);
        } else {
            $master_is_alive = false;
        }
        // Master is still alive?
        if ($master_is_alive) {
            if ($command === 'start' || $command === 'test') {
                echo("Swoole[$start_file] already running\n");
                exit;
            }
        } elseif ($command !== 'start' && $command !== 'test') {
            echo("Swoole[$start_file] not run\n");
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
                echo("Swoole[$start_file] is stoping ...\n");
                // Send stop signal to master process.
                $master_pid && posix_kill($master_pid, SIGTERM);
                // Timeout.
                $timeout = 5;
                $start_time = time();
                // Check master process is still alive?
                while (1) {
                    $master_is_alive = $master_pid && posix_kill($master_pid, SIG_BLOCK);
                    if ($master_is_alive) {
                        // Timeout?
                        if (time() - $start_time >= $timeout) {
                            echo("Swoole[$start_file] stop fail\n");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    echo("Swoole[$start_file] stop success\n");
                    break;
                }
                exit(0);
                break;
            case 'reload':
                posix_kill($manager_pid, SIGUSR1);
                echo("Swoole[$start_file] reload\n");
                exit;
            case 'restart':
                @unlink(self::$pidFile);
                echo("Swoole[$start_file] is stoping ...\n");
                // Send stop signal to master process.
                $master_pid && posix_kill($master_pid, SIGTERM);
                // Timeout.
                $timeout = 5;
                $start_time = time();
                // Check master process is still alive?
                while (1) {
                    $master_is_alive = $master_pid && posix_kill($master_pid, SIG_BLOCK);
                    if ($master_is_alive) {
                        // Timeout?
                        if (time() - $start_time >= $timeout) {
                            echo("Swoole[$start_file] stop fail\n");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    echo("Swoole[$start_file] stop success\n");
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
        $user_info = posix_getpwuid(posix_getuid());
        return $user_info['name'];
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
        echo "\033[1A\n\033[K-------------\033[47;30m SWOOLE_DISTRIBUTED \033[0m--------------\n\033[0m";
        echo 'System:', PHP_OS, "\n";
        echo 'SwooleDistributed version:', self::version, "\n";
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
            case SwooleDispatchClient::SERVER_NAME:
                echo str_pad('TCP',
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('dispatch_server.socket', '--'),
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('dispatch_server.port', '--'),
                    self::$_maxShowLength - 2);
                if (self::$_worker->config->get('dispatch_server.port') == null) {
                    echo " \033[31;40m [CLOSE] \033[0m\n";
                } else {
                    echo " \033[32;40m [OPEN] \033[0m\n";
                }
                break;
            case SwooleDistributedServer::SERVER_NAME:
                echo str_pad('TCP',
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('tcp.socket', '--'),
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('tcp.port', '--'),
                    self::$_maxShowLength - 2);
                if (self::$_worker->tcp_enable??false) {
                    echo " \033[32;40m [OPEN] \033[0m\n";
                } else {
                    echo " \033[31;40m [CLOSE] \033[0m\n";
                }
                echo str_pad('HTTP',
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('http_server.socket', '--'),
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('http_server.port', '--'),
                    self::$_maxShowLength - 2);
                if (self::$_worker->http_enable??false) {
                    echo " \033[32;40m [OPEN] \033[0m\n";
                } else {
                    echo " \033[31;40m [CLOSE] \033[0m\n";
                }
                echo str_pad('WEBSOCKET',
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('http_server.socket', '--'),
                    self::$_maxShowLength), str_pad(self::$_worker->config->get('http_server.port', '--'),
                    self::$_maxShowLength - 2);
                if (self::$_worker->websocket_enable??false) {
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
            $start_file = $argv[0];
            echo "Input \"php $start_file stop\" to quit. Start success.\n";
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
        if ($this->tcp_enable) {
            $this->server = new \swoole_server($this->socket_name, $this->port, SWOOLE_PROCESS, $this->socket_type);
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
     * 数据包编码
     * @param $buffer
     * @return string
     */
    public function encode($buffer)
    {
        $total_length = $this->package_length_type_length + strlen($buffer) - $this->package_body_offset;
        return pack($this->package_length_type, $total_length) . $buffer;
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
        if (!$serv->taskworker && $this->needCoroutine) {//worker进程启动协程调度器
            $this->coroutine = new Coroutine();
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
     * @param $from_id
     * @param $data
     * @return CoreBase\Controller|void
     */
    public function onSwooleReceive($serv, $fd, $from_id, $data)
    {
        $data = substr($data, $this->package_length_type_length);
        //反序列化，出现异常断开连接
        try {
            $client_data = $this->pack->unPack($data);
        } catch (\Exception $e) {
            $serv->close($fd);
            return null;
        }
        //client_data进行处理
        $client_data = $this->route->handleClientData($client_data);
        $controller_name = $this->route->getControllerName();
        $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
        if ($controller_instance != null) {
            if (SwooleServer::$testUnity) {
                $fd = 'self';
                $uid = $fd;
            } else {
                $uid = $serv->connection_info($fd)['uid']??0;
            }
            $method_name = $this->config->get('tcp.method_prefix', '') . $this->route->getMethodName();
            $controller_instance->setClientData($uid, $fd, $client_data, $controller_name, $method_name);
            try {
                if (!method_exists($controller_instance, $method_name)) {
                    $method_name = 'defaultMethod';
                }
                $generator = call_user_func([$controller_instance, $method_name], $this->route->getParams());
                if ($generator instanceof \Generator) {
                    $generatorContext = new GeneratorContext();
                    $generatorContext->setController($controller_instance, $controller_name, $method_name);
                    $this->coroutine->start($generator, $generatorContext);
                }
            } catch (\Throwable $e) {
                call_user_func([$controller_instance, 'onExceptionHandle'], $e);
            }
        }
        return $controller_instance;
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
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return mixed
     */
    public function onSwooleTask($serv, $task_id, $from_id, $data)
    {

    }

    /**
     * onSwooleFinish
     * @param $serv
     * @param $task_id
     * @param $data
     */
    public function onSwooleFinish($serv, $task_id, $data)
    {

    }

    /**
     * onSwoolePipeMessage
     * @param $serv
     * @param $from_worker_id
     * @param $message
     */
    public function onSwoolePipeMessage($serv, $from_worker_id, $message)
    {

    }

    /**
     * onSwooleWorkerError
     * @param $serv
     * @param $worker_id
     * @param $worker_pid
     * @param $exit_code
     */
    public function onSwooleWorkerError($serv, $worker_id, $worker_pid, $exit_code)
    {
        $data = ['worker_id' => $worker_id,
            'worker_pid' => $worker_pid,
            'exit_code' => $exit_code];
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
     * @param array $client_info
     */
    public function onSwoolePacket($server, $data, $client_info)
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
     * @param $error_string
     * @param $filename
     * @param $line
     * @param $symbols
     */
    public function displayErrorHandler($error, $error_string, $filename, $line, $symbols)
    {
        $log = "WORKER Error ";
        $log .= "$error_string ($filename:$line)";
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
        return $this->socket_name ? lcfirst($this->socket_name . ":" . $this->port) : 'none';
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
        if(empty($fdinfo)){
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
