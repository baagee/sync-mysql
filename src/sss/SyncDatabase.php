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
    public static function multiRun($from, $to, $tableConfList)
    {
        foreach ($tableConfList as $tableConf) {
            $process = new \Swoole\Process(function (\Swoole\Process $process) {
                $readData = $process->read(PHP_INT_MAX);
                $readData = json_decode($readData, true);
                (new SyncDatabaseProcess($readData['from'], $readData['to']))->sync($readData['tableConf']);
            }, false, true);

            $process->write(json_encode([
                'from'      => $from,
                'to'        => $to,
                'tableConf' => $tableConf
            ]));
            $process->start();
        }
    }

    public static function run($from, $to, $tableConfList)
    {
        $process = new SyncDatabaseProcess($from, $to);
        foreach ($tableConfList as $value) {
            $process->sync($value);
        }
    }
}
