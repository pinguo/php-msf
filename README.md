# PGWireless Micro Service Framework For PHP

品果微服务框架即“PGWireless Micro Service Framework For PHP”，是Camera360服务器端现代化的PHP服务框架，简称msf或者php-msf，它的核心设计思想是采用协程、异步、并行的创新技术手段提高系统的单机吞吐能力，降低整体服务器成本。

## 主要特性

* 精简版的MVC框架
* IO密集性业务的单机处理能力提升5-10倍
* 代码长驻内存
* 支持对象池
* 支持各种连接池（Redis分布式、master-slave部署结构）
* 支持异步、并行
* 基于PHP yield实现协程
* 内建tcp/http/redis/mysql/mongodb/task等协程客户端
* 原生支持Tcp/Http Server
* RPC Server/Client

## 框架手册目录

* 1.[为什么要研发新的PHP框架?](./doc/01.0-为什么要研发新的PHP框架%3F.md)
 - 1.1. [品果后端技术“痛点”](./doc/01.1-品果后端技术“痛点”.md)
 - 1.2. [传统php-fpm工作模式的问题](./doc/01.2-传统php-fpm工作模式的问题.md)
 - 1.3. [压测数据对比](./doc/01.3-压测数据对比.md)
 - 1.4. [小结](./doc/01.4-小结.md)
* 2.[品果微服务框架研发概览](./doc/02.0-品果微服务框架研发概览.md)
 - 2.1. [新框架研发目标](./doc/02.1-新框架研发目标.md)
 - 2.2. [框架技术选型](./doc/02.2-框架技术选型.md)
 - 2.3. [swoole](./doc/02.3-swoole.md)
 - 2.4. [协程原理](./doc/02.4-协程原理.md)
 - 2.5. [异步、并发](./doc/02.5-步、并发.md)
 - 2.6. [小结](./doc/02.6-小结.md)
* 3.[框架运行环境](./doc/03.0-框架运行环境.md)
 - 3.1 [软件安装](./doc/03.1-软件安装.md)
 - 3.2 [运行代码](./doc/03.2-运行代码.md)
 - 3.3 [supervisor](./doc/03.3-supervisor.md)
 - 3.4 [docker](./doc/03.4-docker.md)
 - 3.5 [小结](./doc/03.5-小结.md)
* 4.[框架结构](./doc/04.0-框架结构.md)
 - 4.1 [结构概述](./doc/04.1-结构概述.md)
 - 4.2 [控制器](./doc/04.2-控制器.md)
 - 4.3 [模型](./doc/04.3-模型.md)
 - 4.4 [视图](./doc/04.4-视图.md)
 - 4.5 [同步任务](./doc/04.5-同步任务.md)
 - 4.6 [配置](./doc/04.6-配置.md)
 - 4.7 [路由](./doc/04.7-路由.md)
 - 4.8 [小结](./doc/04.8-小结.md)
* 5.[框架组件](./doc/05.0-框架组件.md)
 - 5.1 [协程](./doc/05.1-协程.md)
 - 5.2 [类的加载](./doc/05.2-类的加载.md)
 - 5.3 [异步Http Client](./doc/05.3-异步Http%20Client.md)
 - 5.4 [请求上下文](./doc/05.4-请求上下文.md)
 - 5.5 [连接池](./doc/05.5-连接池.md)
 - 5.6 [对象池](./doc/05.6-对象池.md)
 - 5.7 [RPC](./doc/05.7-RPC.md)
 - 5.8 [公共库](./doc/05.8-公共库.md)
 - 5.9 [RESTful](./doc/05.9-RESTful.md)
 - 5.10 [小结](./doc/05.10-小结.md)
* 6.[常见问题](./doc/06.0-常见问题.md)
* 7.[附录](./doc/07.0-附录.md)

## License

Apache License Version 2.0 see [http://www.apache.org/licenses/LICENSE-2.0.html](http://www.apache.org/licenses/LICENSE-2.0.html)