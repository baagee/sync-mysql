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
class SyncDatabaseProcess
{
    /**
     * @var null|\PDO
     */
    protected $from = null;
    /**
     * @var null|\PDO
     */
    protected $to = null;

    /**
     * SyncDatabaseProcess constructor.
     * @param array $from
     * @param array $to
     */
    public function __construct(array $from, array $to)
    {
        $this->from = $this->getConnection($from);
        $this->to   = $this->getConnection($to);
    }

    /**
     * 获取pdo连接对象
     * @param $config   [
     *                  charset
     *                  database
     *                  host
     *                  port
     *                  user
     *                  password
     *                  ]
     * @return \PDO
     */
    protected function getConnection($config)
    {
        $options = [
            // \PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,//禁止多语句查询
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '" . $config['charset'] . "';",// 设置客户端连接字符集
            \PDO::ATTR_TIMEOUT            => 10,// 设置超时
            \PDO::ATTR_PERSISTENT         => true,// 长链接
        ];
        $dsn     = sprintf('mysql:dbname=%s;host=%s;port=%d', $config['database'], $config['host'], $config['port']);
        $pdo     = new \PDO($dsn, $config['user'], $config['password'], $options);
        // $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); //禁用模拟预处理
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->config = $config;
        return $pdo;
    }

    /**
     * @param array $tableConf
     * @return bool
     * @throws \Exception
     */
    public function sync(array $tableConf)
    {
        $tableName = $tableConf['table'] ?? '';
        if (empty($tableName)) {
            throw new \Exception('表名不能为空');
        }
        if (isset($tableConf['truncate'])) {
            $truncate = $tableConf['truncate'] ? true : false;
        } else {
            //默认清空
            $truncate = true;
        }
        $where = empty($tableConf['where']) ? '' : $tableConf['where'];

        $this->print('开始执行同步数据表 ' . $tableName);
        $startTime = microtime(true);
        //是否同步表结构
        $tableDesc = false;
        try {
            //判断两表结构是否一样
            $res = $this->checkTableStructure($tableName);
        } catch (\Exception $e) {
            $this->print(sprintf("【%s】表同步失败: %s", $tableName, $e->getMessage()));
            return false;
        }
        if ($res == false) {
            $msg = sprintf('两个数据库表%s字段不一致，需要同步表结构', $tableName);
            $this->print($msg);
        }
        // 开始导出数据
        $dump = new MySqlDump($this->from->config['host'], $this->from->config['port'], $this->from->config['user'],
            $this->from->config['password'], $this->from->config['database'], $tableName, '', $where, $tableDesc);
        $this->print($dump);
        $dump->execute();
        $localFile = $dump->getExportFile();
        // 开始导入数据
        $importRes = $this->importData($tableName, $truncate, $localFile);
        if ($importRes) {
            $this->print('【' . $tableName . '】数据导入成功，耗时：' . number_format(microtime(true) - $startTime, 2, '.', ''));
        } else {
            $this->print('【' . $tableName . '】数据导入失败，耗时：' . number_format(microtime(true) - $startTime, 2, '.', ''));
        }
        unlink($localFile);
    }

    /**
     * 导入数据
     * @param $tableName
     * @param $truncate
     * @param $localFile
     * @return bool
     */
    protected function importData($tableName, $truncate, $localFile)
    {
        if ($truncate) {
            $this->print("开始清空【" . $tableName . "】已有数据");
            $this->truncate($tableName);
        }

        $cmd = sprintf('mysql -h%s -P%d -u%s -p%s %s -e "source %s"',
            $this->to->config['host'], $this->to->config['port'], $this->to->config['user'], $this->to->config['password'],
            $this->to->config['database'], $localFile
        );
        $this->print($cmd);
        exec($cmd, $ret, $ret2);
        // echo implode(PHP_EOL, $ret) . PHP_EOL;
        if ($ret2 === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $msg
     */
    protected function print($msg)
    {
        echo sprintf('====' . ' PID:' . posix_getpid() . ' %s ===' . PHP_EOL, $msg);
    }

    /**
     * 获取用户参数
     * @param string $tip
     * @param bool   $require
     * @return string
     */
    protected function getInput(string $tip = '请输入：', bool $require = true)
    {
        if ($require) {
            while (true) {
                fwrite(STDOUT, $tip);
                $input = trim(fgets(STDIN));
                if (!empty($input)) {
                    return $input;
                }
            }
        } else {
            fwrite(STDOUT, $tip);
            return trim(fgets(STDIN));
        }
    }

    /**
     * 检查表结构是否一样
     * @param $tableName
     * @return bool 一样返回true, 否则返回false
     * @throws \Exception
     */
    protected function checkTableStructure($tableName)
    {
        $fromStmt = $this->from->query(sprintf('DESC `%s`', $tableName));
        if ($fromStmt == false) {
            throw new \Exception($tableName . '表在From中不存在，无法同步');
        }
        $toStmt = $this->to->query(sprintf('DESC `%s`', $tableName));
        if ($toStmt == false) {
            return false;
        }
        $fromDesc = $fromStmt->fetchAll(\PDO::FETCH_ASSOC);
        $from     = $to = [];
        foreach ($fromDesc as $value) {
            $from[] = implode(':', $value);
        }
        unset($fromDesc);
        $toDesc = $toStmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($toDesc as $value) {
            $to[] = implode(':', $value);
        }
        unset($toDesc);
        $diff1 = array_diff($from, $to);
        $diff2 = array_diff($to, $from);
        return empty($diff1) && empty($diff2);
    }

    /**
     * 清空表
     * @param $tableName
     */
    protected function truncate($tableName)
    {
        $this->to->exec(sprintf('TRUNCATE `%s`', $tableName));
    }
}
