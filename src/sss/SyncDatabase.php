<?php
/**
 * Desc:
 * User: baagee
 * Date: 2019/6/3
 * Time: 10:28
 */

namespace Sss;

/**
 * Class SyncDatabase
 * @package Sss
 */
class SyncDatabase
{
    public static function multiRun($from, $to, $tableConfList, $pageSize = 3000)
    {
        foreach ($tableConfList as $tableConf) {
            $process = new \Swoole\Process(function (\Swoole\Process $process) {
                $readData = $process->read(PHP_INT_MAX);
                $readData = json_decode($readData, true);
                (new SyncDatabaseProcess($readData['from'], $readData['to'], $readData['pageSize']))->sync($readData['tableConf']);
            }, false, true);

            $process->write(json_encode([
                'from'      => $from,
                'to'        => $to,
                'pageSize'  => $pageSize,
                'tableConf' => $tableConf
            ]));
            $process->start();
        }
    }

    public static function run($from, $to, $tableConfList, $pageSize = 3000)
    {
        $process = new SyncDatabaseProcess($from, $to, $pageSize);
        foreach ($tableConfList as $value) {
            $process->sync($value);
        }
    }
}
