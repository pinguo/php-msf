<?php
/**
 * @desc: MongoDb配置文件
 * @author: leandre <niulingyun@camera360.com>
 * @date: 2017/2/13
 * @copyright All rights reserved.
 */

/**
 * 本地环境
 */
$config['mongodb']['test']['server'] = 'mongodb://127.0.0.1:27017';
$config['mongodb']['test']['options'] = ['connect' => true];
$config['mongodb']['test']['driverOptions'] = [];

return $config;