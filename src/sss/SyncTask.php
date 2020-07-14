<?php
/**
 * Desc: 同步数据任务
 * User: baagee
 * Date: 2020/7/14
 * Time: 下午4:36
 */

namespace Sss;

use BaAGee\AsyncTask\TaskBase;

class SyncTask extends TaskBase
{
    public function run($params = [])
    {
        $from = unserialize($params[0]);
        $to = unserialize($params[1]);
        $tableConf = unserialize($params[2]);
        $pageSize = unserialize($params[3]);
        $process = new SyncDatabaseProcess($from, $to, $pageSize);
        $process->sync($tableConf);
    }
}