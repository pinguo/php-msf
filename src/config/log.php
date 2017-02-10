<?php
/**
 * 日志配置
 * Created by PhpStorm.
 * User: niulingyun
 * Date: 17-1-11
 * Time: 下午2:40
 */

$config['server']['log'] = [
    'handlers' => [
        'application' => [
            'levelList' => [
                \Monolog\Logger::WARNING,
                \Monolog\Logger::ALERT,
                \Monolog\Logger::CRITICAL,
                \Monolog\Logger::ERROR,
                \Monolog\Logger::WARNING
            ],
            'dateFormat' => "Y/m/d H:i:s",
            'format' => "%datetime% [%level_name%] [%channel%] [logid:%logId%] %context% %message% %extra%\n",
            'stream' => ROOT_PATH . '/runtime/logs/application.log',
            'buffer' => 0
        ],
        'notice' => [
            'levelList' => [
                \Monolog\Logger::NOTICE,
                \Monolog\Logger::INFO,
                \Monolog\Logger::DEBUG
            ],
            'dateFormat' => "Y/m/d H:i:s",
            'format' => "%datetime% [%level_name%] [%channel%] [logid:%logId%] %context% %message%  %extra%\n",
            'stream' => ROOT_PATH . '/runtime/logs/notice.log',
            'buffer' => 1
        ]
    ]
];

return $config;