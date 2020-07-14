# mysql数据同步

#### 默认会同步表结构，如果安装了Swoole扩展会自动选择多进程同步，一个进程同步一个表,没有swoole借助第三方composer包实现多进程同步

# 执行composer install，然后config配置要同步的数据库和表，然后运行run.php