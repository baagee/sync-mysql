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
    const PAGE_SIZE = 2000;

    /**
     * 一次批量导入最大数量
     */
    const BATCH_INSERT_ROWS_NUMBER = 100;

    /**
     * SyncDatabase constructor.
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
            \PDO::ATTR_TIMEOUT            => 20// 设置超时
        ];
        $dsn     = sprintf('mysql:dbname=%s;host=%s;port=%d', $config['database'], $config['host'], $config['port']);
        $pdo     = new \PDO($dsn, $config['user'], $config['password'], $options);
        // $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); //禁用模拟预处理
        // $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); //禁用模拟预处理
        $pdo->config = $config;
        return $pdo;
    }

    /**
     * @param array $tableNames
     * @throws \Exception
     */
    public function sync(array $tableNames = [])
    {
        if (empty($tableNames)) {
            // 获取目标数据库的所有表
            $list       = $this->from->query('SHOW TABLES')->fetchAll(\PDO::FETCH_ASSOC);
            $database   = $this->from->config['database'];
            $tableNames = array_column($list, 'Tables_in_' . $database);
        }
        foreach ($tableNames as $itemArr) {
            $tableName = $itemArr['table'] ?? '';
            if (empty($tableName)) {
                continue;
            }
            $truncate = $itemArr['truncate'] ? true : false;
            $where    = empty($itemArr['where']) ? 'true' : $itemArr['where'];

            $this->print('开始执行同步数据表 ' . $tableName);
            $startTime = microtime(true);
            $this->syncData($tableName, $where, $truncate);
            $this->print($tableName . ' 数据导入完毕，耗时：' . number_format(microtime(true) - $startTime, 2, '.', ''));
            // break;
        }
    }

    /**
     * @param $msg
     */
    protected function print($msg)
    {
        echo sprintf('==== %s ===' . PHP_EOL, $msg);
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
        $createTable    = $this->from->query(sprintf('SHOW CREATE TABLE `%s`', $tableName))->fetchAll(\PDO::FETCH_ASSOC);
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
     * 同步数据
     * @param string $tableName
     * @param string $where
     * @param bool   $truncate
     * @return bool
     * @throws \Exception
     */
    protected function syncData(string $tableName, string $where, bool $truncate)
    {
        // 检查表结构是否一样
        $res = $this->checkTableStructure($tableName);
        if ($res == false) {
            // 不一样 是否需要同步表结构
            $an = $this->getInput(sprintf('两个数据库表%s字段不一致，是否同步？(yes/no)', $tableName));
            if ($an == 'yes') {
                $this->syncTableStructure($tableName);
            } else {
                $this->print(sprintf('跳过同步%s表', $tableName));
                return false;
            }
        }

        if ($truncate) {
            // 清空测试环境司机信息
            $this->truncate($tableName);
        }

        return $this->syncTableData($tableName, $where);
    }

    /**
     * 同步表数据
     * @param $tableName
     * @param $where
     * @return bool
     * @throws \Exception
     */
    protected function syncTableData($tableName, $where)
    {
        // 总数量
        $totalCount = $this->getTotalCount($tableName, $where);
        $page       = ceil($totalCount / self::PAGE_SIZE);
        $lineCount  = 0;
        for ($i = 1; $i <= $page; $i++) {
            // 分页导入
            $list = $this->yieldRow($tableName, $i, $where);
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
                $this->print($tableName . ' 数据导入 ' . number_format($lineCount / $totalCount * 100, 2, '.', '') . '%');
            }
            unset($list);
        }
        return true;
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
        $sql         = 'REPLACE INTO `' . $tableName . '` (';
        $fields      = [];
        $zz          = '';
        $prepareData = [];
        foreach ($batchInsertRows as $i => $item_array) {
            $z = '(';
            foreach ($item_array as $k => $v) {
                if (!in_array($k, $fields)) {
                    $fields[] = $k;
                }
                $z                                .= ':' . $k . '_' . $i . ', ';
                $prepareData[':' . $k . '_' . $i] = $v;
            }
            $zz .= rtrim($z, ', ') . '),';
        }
        $fields = '`' . implode('`, `', $fields) . '`';
        $sql    .= $fields;
        $sql    = rtrim($sql, ', ') . ') VALUES ' . rtrim($zz, ',');
        $stmt   = $this->to->prepare($sql);
        $res    = $stmt->execute($prepareData);
        if ($res) {
            // $this->print(sprintf('成功导入%d条数据', count($batchInsertRows)));
        } else {
            $this->print(sprintf('WARNING %s导入数据失败', $tableName));
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
        $sql  = sprintf('SELECT count(*) AS c FROM `%s` WHERE %s', $tableName, $where);
        $stmt = $this->from->query($sql);
        if ($stmt == false) {
            throw new \Exception('查询数据失败:' . $sql);
        }
        return $stmt->fetch(\PDO::FETCH_ASSOC)['c'];
    }

    /**
     * 返回数据生成器
     * @param $tableName
     * @param $p
     * @param $where
     * @return \Generator
     * @throws \Exception
     */
    protected function yieldRow($tableName, $p, $where)
    {
        $sql  = sprintf('SELECT * FROM `%s` WHERE %s LIMIT %d, %d', $tableName, $where, ($p - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        $stmt = $this->from->prepare($sql);
        $rrr  = $stmt->execute();
        if ($rrr === false || $stmt == false) {
            throw new \Exception($tableName . ' 查询数据失败:' . $sql);
        }
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }
}
