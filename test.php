<?php
/**
 * Desc: 使用示例
 * User: baagee
 * Date: 2019/7/5
 * Time: 20:22
 */


ini_set('display_errors', 1);

include_once __DIR__ . '/vendor/autoload.php';

// 线上
$from = [
    'host'     => '1045374',
    'port'     => 5436,
    'charset'  => 'utf8',
    'database' => '346',
    'user'     => '453',
    'password' => '457456'
];

// 开发机
$to = [
    'host'     => '',
    'port'     => 5100,
    'charset'  => 'utf8',
    'database' => '235234',
    'user'     => '46345',
    'password' => '56743@ss'
];

$tables = [
    // [
    //     'table'    => 'user',//表名
    //     'truncate' => true,// 是否清空表
    //     'where'    => ''// 筛选那些数据需要同步
    // ],
    // ['table'    => 'driver',
    //  'truncate' => true,],
    // ['table'    => 'vehicle',
    //  'truncate' => true,],
    // ['table'    => 'matrix',
    //  'truncate' => true,],
    ['table'    => 'area',
     'truncate' => false,],
];
try {
    if (class_exists(\Swoole\Process::class)) {
        \Sss\SyncDatabase::multiRun($from, $to, $tables);
    } else {
        \Sss\SyncDatabase::run($from, $to, $tables);
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}

echo "OVER" . PHP_EOL;