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
     * yield查询每页页数
     */
    protected $pageSize = 3000;

    /**
     * 一次批量导入最大数量
     */
    const BATCH_INSERT_ROWS_NUMBER = 500;

    /**
     * SyncDatabaseProcess constructor.
     * @param array $from
     * @param array $to
     * @param int   $pageSize
     */
    public function __construct(array $from, array $to, $pageSize = 3000)
    {
        $this->from = $this->getConnection($from);
        $this->to = $this->getConnection($to);
        if (!empty($pageSize) && is_numeric($pageSize)) {
            $this->pageSize = (int)$pageSize;
        }
        echo PHP_EOL;
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
            \PDO::ATTR_TIMEOUT => 10,// 设置超时
            \PDO::ATTR_PERSISTENT => true,// 长链接
        ];
        $dsn = sprintf('mysql:dbname=%s;host=%s;port=%d', $config['database'], $config['host'], $config['port']);
        $pdo = new \PDO($dsn, $config['user'], $config['password'], $options);
        // $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); //禁用模拟预处理
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->config = $config;
        return $pdo;
    }

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
        $where = empty($tableConf['where']) ? 'true' : $tableConf['where'];

        $this->print('开始执行同步数据表 ' . $tableName);
        $startTime = microtime(true);
        $this->to->exec('set autocommit=0');
        $this->to->exec('SET unique_checks=0');
        $this->to->exec('SET foreign_key_checks=0');
        $this->syncData($tableName, $where, $truncate);
        $this->to->exec('set autocommit=1');
        $this->to->exec('SET unique_checks=1');
        $this->to->exec('SET foreign_key_checks=1');
        $this->print('【' . $tableName . '】数据导入完毕，耗时：' . number_format(microtime(true) - $startTime, 2, '.', ''));
    }

    /**
     * @param $msg
     */
    protected function print($msg)
    {
        echo sprintf('==== PID:%-8d  %s ===' . PHP_EOL, posix_getpid(), $msg);
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
     * 同步表结构
     * @param $tableName
     * @return bool
     */
    protected function syncTableStructure($tableName)
    {
        $this->to->exec(sprintf('DROP TABLE `%s`', $tableName));
        $createTable = $this->from->query(sprintf('SHOW CREATE TABLE `%s`', $tableName))->fetchAll(\PDO::FETCH_ASSOC);
        $createTableSql = $createTable[0]['Create Table'];
        $this->to->exec($createTableSql);
        $this->print(sprintf('同步表%s结构成功', $tableName));
        return true;
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
        $from = $to = [];
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

    /* protected function getFromTableList()
     {
         $stmt = $this->from->query('SHOW TABLES');
         $tableList=$stmt->fetchAll(\PDO::FETCH_ASSOC);
         var_dump($tableList);
     }*/

    /**
     * 同步数据
     * @param string $tableName
     * @param string $where
     * @param bool   $truncate
     * @return bool
     * @throws \Exception
     */
    protected function syncData(string $tableName, string $where, bool $truncate)
    {
        $fields = '*';
        // 检查表结构是否一样
        try {
            $res = $this->checkTableStructure($tableName);
            if ($res == false) {
                $this->syncTableStructure($tableName);

                // 不一样 是否需要同步表结构
                // $an = $this->getInput(sprintf('两个数据库表%s字段不一致，是否同步？(yes/no)', $tableName));
                // if ($an == 'yes') {
                //     $this->syncTableStructure($tableName);
                // } else {
                //     $this->print(sprintf('跳过同步%s表', $tableName));
                //     return false;
                // }
            }

            if ($truncate) {
                // 清空测试环境信息
                $this->truncate($tableName);
            }
            $fields = $this->getSelectFields($tableName);
            if (empty($fields)) {
                return false;
            }
            $fields = '`' . implode('`,`', $fields) . '`';
        } catch (\Exception $e) {
            $this->print('【【【【重要的事情说三遍】】】】');
            for ($i = 0; $i < 3; $i++) {
                $this->print($tableName . '表结构同步失败：' . $e->getMessage());
            }
            $this->print('【【【【重要的事情说三遍】】】】');
            return false;
        }
        return $this->syncTableData($tableName, $fields, $where);
    }

    protected function getSelectFields($tableName)
    {
        $fromStmt = $this->to->query(sprintf('DESC `%s`', $tableName));
        if ($fromStmt == false) {
            return false;
        }
        $fromDesc = $fromStmt->fetchAll(\PDO::FETCH_ASSOC);
        // var_dump($fromDesc);die;
        $fields = [];
        foreach ($fromDesc as $item) {
            if (stripos($item['Extra'], 'VIRTUAL GENERATED') === false) {
                $fields[] = $item['Field'];
            }
        }
        return $fields;
    }

    /**
     * 同步表数据
     * @param $tableName
     * @param $fields
     * @param $where
     * @return bool
     * @throws \Exception
     */
    protected function syncTableData($tableName, $fields, $where)
    {
        try {
            // 总数量
            $totalCount = $this->getTotalCount($tableName, $where);
            $page = ceil($totalCount / $this->pageSize);
            $lineCount = 0;
            for ($i = 1; $i <= $page; $i++) {
                // 分页导入
                $list = $this->yieldRow($tableName, $i, $where, $fields);
                if ($list instanceof \Generator) {
                    $batchInsertRows = [];
                    foreach ($list as $index => $row) {
                        $batchInsertRows[] = $row;
                        $lineCount++;
                        if ($index !== 0 && (($index + 1) % self::BATCH_INSERT_ROWS_NUMBER == 0)) {
                            $this->batchInsert($tableName, $batchInsertRows);
                            $batchInsertRows = [];
                        }
                    }

                    if (!empty($batchInsertRows)) {
                        $this->batchInsert($tableName, $batchInsertRows);
                    }
                    // $persent = number_format($lineCount / $totalCount * 100, 2, '.', '') . '%';
                    // $this->print(sprintf("【%s】总数据%d 导入%d %s", $tableName, $totalCount, $lineCount, $persent));
                    $this->show($tableName, $totalCount, $lineCount);
                    // $this->process->current(intval($lineCount / $totalCount * 100));
                    // $this->process->update($lineCount);
                }
                unset($list);
            }
        } catch (\Exception $e) {
            $this->print('【【【【重要的事情说三遍】】】】');
            for ($i = 0; $i < 3; $i++) {
                $this->print('【' . $tableName . '】数据导入失败：' . $e->getMessage());
            }
            $this->print('【【【【重要的事情说三遍】】】】');
        }
        return true;
    }

    protected function show($table, $total, $curCount)
    {
        $this->print(sprintf('Table:%-40s Total:%-10d insert:%-10d %.2f%%', $table, $total, $curCount, $curCount / $total * 100));
    }

    /**
     * 清空表
     * @param $tableName
     */
    protected function truncate($tableName)
    {
        $this->to->exec(sprintf('TRUNCATE `%s`', $tableName));
    }

    /**
     * 批量插入
     * @param $tableName
     * @param $batchInsertRows
     */
    protected function batchInsert($tableName, $batchInsertRows)
    {
        $sql = 'REPLACE INTO `' . $tableName . '` (';
        $fields = [];
        $zz = '';
        $prepareData = [];
        foreach ($batchInsertRows as $i => $item_array) {
            $z = '(';
            foreach ($item_array as $k => $v) {
                if (!in_array($k, $fields)) {
                    $fields[] = $k;
                }
                $z .= ':' . $k . '_' . $i . ', ';
                $prepareData[':' . $k . '_' . $i] = $v;
            }
            $zz .= rtrim($z, ', ') . '),';
        }
        $fields = '`' . implode('`, `', $fields) . '`';
        $sql .= $fields;
        $sql = rtrim($sql, ', ') . ') VALUES ' . rtrim($zz, ',');
        $stmt = $this->to->prepare($sql);
        $res = $stmt->execute($prepareData);
        if ($res) {
            // $this->print(sprintf('成功导入%d条数据', count($batchInsertRows)));
            $this->to->exec('commit');
        } else {
            $this->print(sprintf('WARNING 【%s】导入数据失败', $tableName));
        }
    }

    /**
     * 获取要同步数据总数量
     * @param $tableName
     * @param $where
     * @return mixed
     * @throws \Exception
     */
    protected function getTotalCount($tableName, $where)
    {
        $sql = sprintf('SELECT count(*) AS c FROM `%s` WHERE %s', $tableName, $where);
        $stmt = $this->from->query($sql);
        if ($stmt == false) {
            throw new \Exception('查询数据失败:' . $sql);
        }
        return $stmt->fetch(\PDO::FETCH_ASSOC)['c'];
    }

    /**
     * 返回数据生成器
     * @param        $tableName
     * @param        $p
     * @param        $where
     * @param string $fields
     * @return \Generator
     * @throws \Exception
     */
    protected function yieldRow($tableName, $p, $where, $fields = '*')
    {
        $sql = sprintf('SELECT %s FROM `%s` WHERE %s LIMIT %d, %d', $fields, $tableName, $where, ($p - 1) * $this->pageSize, $this->pageSize);
        $stmt = $this->from->prepare($sql);
        $rrr = $stmt->execute();
        if ($rrr === false || $stmt == false) {
            throw new \Exception($tableName . ' 查询数据失败:' . $sql);
        }
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }
}
