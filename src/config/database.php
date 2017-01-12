<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午4:49
 */
$config['database']['active'] = 'test';
$config['database']['test']['host'] = '192.168.20.196';
$config['database']['test']['port'] = '3306';
$config['database']['test']['user'] = 'root';
$config['database']['test']['password'] = '123456';
$config['database']['test']['database'] = 'youwo_dliao';
$config['database']['test']['charset'] = 'utf8';
$config['database']['asyn_max_count'] = 10;
return $config;
