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
    /*
     * swoole多进程同步
     */
    public static function swooleRun($from, $to, $tableConfList, $pageSize = 3000)
    {
        foreach ($tableConfList as $tableConf) {
            $process = new \Swoole\Process(function (\Swoole\Process $process) {
                $readData = $process->read(PHP_INT_MAX);
                $readData = json_decode($readData, true);
                (new SyncDatabaseProcess($readData['from'], $readData['to'], $readData['pageSize']))->sync($readData['tableConf']);
            }, false, true);

            $process->write(json_encode([
                'from' => $from,
                'to' => $to,
                'pageSize' => $pageSize,
                'tableConf' => $tableConf
            ]));
            $process->start();
            // usleep(400000);
        }
    }

    /*
     * 借助第三方包使用多进程同步
     */
    public static function taskRun($from, $to, $tableConfList, $pageSize = 3000)
    {
        $baseDir = getcwd();
        $outputDir = $baseDir . '/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        \BaAGee\AsyncTask\TaskScheduler::init($outputDir . '/lock', 20, $outputDir);
        $task = \BaAGee\AsyncTask\TaskScheduler::getInstance();
        foreach ($tableConfList as $tableConf) {
            $task->runTask(\Sss\SyncTask::class, [serialize($from), serialize($to), serialize($tableConf), $pageSize]);
            echo sprintf('%s同步任务已启动，日志路径：%s' . PHP_EOL, $tableConf['table'], $outputDir);
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
