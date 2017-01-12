此目录为单元测试目录  
该目录下存放单元测试代码，不应该存在有文件夹，如果存在文件夹，那么此文件夹中的内容将被忽略  
```
php start_swoole_server.php test 
```
将执行该文件夹中所有的测试用例  
```
php start_swoole_server.php test ServerMysqlTest
```
将执行该文件夹中ServerMysqlTest.php的测试用例  