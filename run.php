<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


$curDir = dirname(__FILE__);
require_once $curDir . '/vendor/autoload.php';

$config = include_once $curDir . '/config.php';

try {
    if (class_exists(\Swoole\Process::class)) {
        \Sss\SyncDatabase::multiRun($config['from'], $config['to'], $config['table'], $config['page_size']);
    } else {
        \Sss\SyncDatabase::run($config['from'], $config['to'], $config['table'], $config['page_size']);
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
