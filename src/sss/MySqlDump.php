<?php
/**
 * Desc:
 * User: baagee
 * Date: 2020/3/17
 * Time: 下午7:27
 */

namespace Sss;

/**
 * Class MySqlDump
 * @package Sss
 */
class MySqlDump
{
    /**
     * @var string
     */
    protected $host = '127.0.0.1';
    /**
     * @var int
     */
    protected $port = 3306;
    /**
     * @var string
     */
    protected $user = '';
    /**
     * @var string
     */
    protected $password = '';
    /**
     * @var string
     */
    protected $database = '';
    /**
     * @var string
     */
    protected $table = '';
    /**
     * @var string
     */
    protected $exportFile = '';
    /**
     * @var string
     */
    protected $where = '';
    /**
     * @var bool
     */
    protected $createInfo = true;


    /**
     * MySqlDump constructor.
     * @param        $host
     * @param        $port
     * @param        $user
     * @param        $password
     * @param        $database
     * @param        $table
     * @param string $exportFile
     * @param string $where
     * @param bool   $createInfo
     */
    public function __construct($host, $port, $user, $password, $database, $table,
                                $exportFile = '', $where = '', $createInfo = true)
    {
        $this->host       = $host;
        $this->port       = $port;
        $this->user       = $user;
        $this->password   = $password;
        $this->database   = $database;
        $this->table      = $table;
        $this->createInfo = $createInfo;

        if (empty($exportFile)) {
            $exportFile = sprintf(getcwd() . DIRECTORY_SEPARATOR . $database . '.' . $table . '.sql');
        } elseif (is_file($exportFile)) {
            unlink($exportFile);
        }
        $this->exportFile = $exportFile;
        $where            = trim($where);
        if (!empty($where)) {
            $this->where = $where;
        }
    }

    /**
     * 执行导出
     */
    public function execute()
    {
        $cmd = $this->getCommand();
        exec($cmd, $output);
        // echo implode(PHP_EOL, $output) . PHP_EOL;
    }

    /**
     * @return string
     */
    public function getExportFile(): string
    {
        return $this->exportFile;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        $cmd = sprintf('mysqldump -u%s -p%s -h%s -P%d --add-locks=0 --single-transaction %s %s --result-file=%s',
            $this->user, $this->password, $this->host, $this->port, $this->database, $this->table, $this->exportFile);
        if (!empty($this->where)) {
            $cmd .= ' --where="' . $this->where . '"';
        }
        if ($this->createInfo == false) {
            $cmd .= ' --no-create-info';
        }
        return $cmd;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getCommand();
    }

    /**
     *
     */
    private function __clone()
    {
    }
}
