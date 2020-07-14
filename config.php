<?php
/**
 * 配置文件
 */

return [
    /*******************************线上备库********************************/
    'from' => [
        'host' => '',
        'port' => 6655,
        'charset' => 'utf8',
        'database' => '',
        'user' => '',
        'password' => ''
    ],
    /*******************************线上备库********************************/

    // /*******************************我的环境********************************/
    'to' => [
        'host' => '',
        'port' => 2222,
        'charset' => 'utf8',
        'database' => '',
        'user' => '',
        'password' => ''
    ],
    /*******************************我的环境********************************/

    'page_size' => 3000,//一次查询数量条数
    //一次同步不要超过20个表
    'table' => [
        // ['table' => 'batch_group_rule', 'truncate' => true, 'where' => 'create_time<"2020-05-15 19:33:47"'],
        // ['table' => 'main_account', 'truncate' => true],
        // ['table' => 'bms_order', 'truncate' => true],
        // ['table' => 'privilege', 'truncate' => true],

    ],
];