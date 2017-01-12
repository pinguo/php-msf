<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-14
 * Time: 下午1:58
 */
/**
 * tcp访问时方法的前缀
 */
$config['tcp']['method_prefix'] = '';
/**
 * http访问时方法的前缀
 */
$config['http']['method_prefix'] = 'http_';
/**
 * websocket访问时方法的前缀
 */
$config['websocket']['method_prefix'] = '';

//http服务器绑定的真实的域名或者ip:port，一定要填对,否则获取不到文件的绝对路径
$config['http']['domain'] = 'http://localhost:8081';

//默认访问的页面
$config['http']['index'] = 'index.html';

//是否服务器启动时自动清除群组信息
$config['autoClearGroup'] = false;

/**
 * 设置域名和Root之间的映射关系
 */

$config['http']['root'] = [
    'localhost' =>
        [
            'root' => 'localhost',
            'index' => 'index.html'
        ]
];

return $config;
