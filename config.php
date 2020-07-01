<?php
/**
 * 配置文件
 */

return [
    /*******************************线上备库********************************/
    'from' => [
        'host' => '127.0.0.1',
        'port' => 5418,
        'charset' => 'utf8',
        'database' => 'sdgsa',
        'user' => 'hadsgfas',
        'password' => 'I2Xydsffgsdf'
    ],
    /*******************************线上备库********************************/

    // /*******************************我的环境********************************/
    'to' => [
        'host' => '128.0.0.1',
        'port' => 5728,
        'charset' => 'utf8',
        'database' => 'sdga',
        'user' => 'fd12',
        'password' => 'dhdsfhd'
    ],
    /*******************************我的环境********************************/

    'page_size' => 1000,//一次查询数量条数
    'table' => [
        // ['table' => 'tms_line', 'truncate' => true,'where'=>'create_time>="2020-03-29"'],
        // ['table' => 'tms_line_sort', 'truncate' => true,'where'=>'create_time>="2020-03-29"'],
        // ['table' => 'tms_line_sort_deliver', 'truncate' => true,'where'=>'create_time>="2020-03-29"'],
        // ['table' => 'tms_scheduling', 'truncate' => true,'where'=>'create_time>="2020-03-29"'],
        // ['table' => 'tms_waybill', 'truncate' => true,'where'=>'create_time>="2020-03-29"'],
    ],
];