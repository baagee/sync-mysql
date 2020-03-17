<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/vendor/autoload.php';

$config = include_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

try {
    if (class_exists(\Swoole\Process::class)) {
        \Sss\SyncDatabase::multiRun($config['from'], $config['to'], $config['table']);
    } else {
        \Sss\SyncDatabase::run($config['from'], $config['to'], $config['table']);
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
