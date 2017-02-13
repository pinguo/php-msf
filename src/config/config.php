<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-14
 * Time: 下午1:58
 */
/**
 * http服务器设置
 */
$config['http_server']['enable'] = true;
$config['http_server']['socket'] = '0.0.0.0';
$config['http_server']['port'] = 8081;
/**
 * 是否启用websocket
 */
$config['websocket']['enable'] = false;
/*WEBSOCKET_OPCODE_TEXT = 0x1，UTF-8文本字符数据
WEBSOCKET_OPCODE_BINARY = 0x2，二进制数据*/
$config['websocket']['opcode'] = WEBSOCKET_OPCODE_BINARY;

/**
 * tcp设置
 */
$config['tcp']['enable'] = true;
$config['tcp']['socket'] = '0.0.0.0';
$config['tcp']['port'] = 9090;

/**
 * 服务器设置
 */
$config['server']['process_title'] = 'msf';
$config['server']['dispatch_port'] = 9991;
$config['server']['send_use_task_num'] = 20;
$config['server']['runtime_path'] = ROOT_PATH . '/runtime/';
$config['server']['pid_path'] = $config['server']['runtime_path'] . 'pids/';

$config['server']['pack_tool'] = 'JsonPack';
$config['server']['route_tool'] = 'NormalRoute';
$config['server']['set'] = [
    'reactor_num' => 2, //reactor thread num
    'worker_num' => 4,    //worker process num
    'backlog' => 128,   //listen backlog
    'open_tcp_nodelay' => 1,
    'dispatch_mode' => 5,
    'task_worker_num' => 5,
    'enable_reuse_port' => true,
    'heartbeat_idle_time' => 120,//2分钟后没消息自动释放连接
    'heartbeat_check_interval' => 60,//1分钟检测一次
];
$config['server']['probuf_set'] = [
    'open_length_check' => 1,
    'package_length_type' => 'N',
    'package_length_offset' => 0,       //第N个字节是包长度的值
    'package_body_offset' => 0,       //第几个字节开始计算长度
    'package_max_length' => 2000000,  //协议最大长度)
];
/**
 * dispatch服务器设置
 */
$config['dispatch_server']['socket'] = '0.0.0.0';
$config['dispatch_server']['port'] = 60000;
$config['dispatch_server']['password'] = 'Hello Dispatch';
$config['dispatch_server']['set'] = [
    'reactor_num' => 2, //reactor thread num
    'worker_num' => 4,    //worker process num
    'backlog' => 128,   //listen backlog
    'open_tcp_nodelay' => 1,
    'dispatch_mode' => 3,
    'enable_reuse_port' => true,
];


//协程超时时间
$config['coroution']['timerOut'] = 5000;

//异步服务是否启动一个新进程（启动后异步效率会降低2倍，但维护连接池只有一个）
$config['asyn_process_enable'] = false;

//是否启动集群模式
$config['use_dispatch'] = false;

//是否启用自动reload
$config['auto_reload_enable'] = true;
return $config;
