<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-14
 * Time: 下午1:58
 */

/**
 * 选择数据库环境
 */
$config['redis']['active'] = 'test';

/**
 * 本地环境
 */
$config['redis']['test']['ip'] = '127.0.0.1';
$config['redis']['test']['port'] = 6379;
//$config['redis']['test']['select'] = 1;
//$config['redis']['test']['password'] = '123456';
$config['redis']['asyn_max_count'] = 10;

/**
 * 本地环境2
 */
$config['redis']['test2']['ip'] = '192.168.21.10';
$config['redis']['test2']['port'] = 6379;
$config['redis']['test2']['select'] = 2;
$config['redis']['test2']['password'] = '123456';
$config['redis']['asyn_max_count'] = 10;

/**
 * 这个不要删除，dispatch使用的redis环境
 * dispatch使用的环境
 */
$config['redis']['dispatch']['ip'] = 'unix:/var/run/redis/redis.sock';
$config['redis']['dispatch']['port'] = 0;
$config['redis']['dispatch']['select'] = 1;
$config['redis']['dispatch']['password'] = '123456';
$config['redis']['asyn_max_count'] = 10;

/**
 * 最终的返回，固定写这里
 */
return $config;
