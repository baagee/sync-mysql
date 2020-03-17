<?php
/**
 * 配置文件
 */

return [
    'from'  => [
        'host'     => '',
        'port'     => 9900,
        'charset'  => 'utf8',
        'database' => 'wet',
        'user'     => 'erge',
        'password' => '2reth'
    ],
    'to'    => [
        'host'     => '',
        'port'     => 1234,
        'charset'  => 'utf8',
        'database' => '',
        'user'     => '',
        'password' => '1q2w3e@sf'
    ],
    'table' => [
        ['table' => 'vehicle', 'truncate' => false],
        ['table' => 'driver', 'where' => 'id>10700'],
    ],
];
