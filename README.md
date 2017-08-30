# Micro Service Framework For PHP

PHP微服务框架即“Micro Service Framework For PHP”，是Camera360社区服务器端团队基于Swoole自主研发的现代化的PHP服务框架，简称msf或者php-msf，它的核心设计思想是采用协程、异步、并行的创新技术手段提高系统的单机吞吐能力，降低整体服务器成本。

## 主要特性

* 精简版的MVC框架
* IO密集性业务的单机处理能力提升5-10倍
* 代码长驻内存
* 支持对象池
* 支持Redis连接池、MySQL连接池
* 支持Redis分布式、master-slave部署结构的集群
* 支持异步、并行
* 基于PHP Yield实现协程
* 内建http/redis/mysql/mongodb/task等协程客户端
* 纯异步的Http Server
* RPC Server/Client
* 支持命令行模式
* 支持独立进程的定时器
* 支持独立配置进程

## 定位

我们专注打造稳定高性能纯异步基于HTTP的微服务框架，作为nginx+php-fpm的替代技术栈实现架构的微服务化;而Tcp/WebSocket Server将作为插件的形势支持，或者作为其他独立的开源项目。

## 原则

### 稳定

php-msf经受了Camera360社区服务大流量、高并发的洗礼，稳定性得到充分验证。稳定性是我们花了大量时间、精力去解决的最重要问题，是三大原则的最重要原则。

### 高性能

IO密集性业务的单机处理能力提升5-10倍，这是生产环境中得出的真实数据，如Camera360社区某聚合服务在流量高峰需要40台服务器抗住流量，而采用php-msf重构之后只需要4台相同配置的服务器就可以抗住所有流量。

### 简单

由于Swoole复杂的进程模型，并且有同步阻塞和异步非阻塞之分，所以在运行相同代码逻辑时，可能在调用方式、传递参数都不一致，从而直线拉高了学习成本，我们为了屏蔽低层的差异，做了大量的工作，实现和传统MVC框架的唯一区别在于添加“yield”关键字。

## 协程

目前社区有几个PHP开源项目支持协程，它们大多采用Generator+Yield来实现，但是实现的细微差别会导致性能相差甚远，我们应该认识到协程能够以同步的代码书写方式而运行异步逻辑，故协程调度器的性能一定要足够的高，php-msf的协程调度性能是原生异步回调方式的80%，也就是说某个API采用原生异步回调写法QPS为10000，通过php-msf协程调度器调度QPS为8000。

## 为什么是微服务框架？

目前php-msf还在起步阶段，我们花了大量的时间和精力解决稳定性、高性能、内存问题，因为我们认为“基石”是“万丈高楼”的最基本的保障，只有基础打得牢，才能将“大楼”建设得“更高”。3.0版本是我们开源的起始版本，是我们迈出的重要一步，接下来我们重点会是分布式微服务框架的打磨。

另外，由于基于PHP长驻进程，并直接解析HTTP或者TCP请求，这是服务化最重要的支撑，基于此我们可以做很多原来不敢去实现的想法，总之想像空间很大。

## 感谢

php-msf最开始基于[SwooleDistributed-1.7.x](https://github.com/tmtbe/SwooleDistributed/)开发，而此次开源版本中，连接池主要采用了SD的实现。由于我们框架定位、解决的业务场景、稳定性的要求、代码风格等差异太大，故我们自主研发微服务框架，每个框架都有自己的特色和优点，选择合适自己公司和业务场景的框架最重要。同时在此也感谢[白猫](https://github.com/tmtbe)；另外，在研发php-msf框架及生产环境应用过程中，遇到很多低层问题，不过都一一解决，而这些问题能够解决最重要就是[韩天峰-Rango](https://github.com/matyhtf)的大力支持，在此深表感谢。

## 文档

API Document: [类文档](https://rawgit.com/PGWireless/php-msf/master/api/index.html)

框架手册: [目录](./doc/preface-目录.md)

示例项目: [demo](https://github.com/PGWireless/php-msf/tree/demo)

## License

Apache License Version 2.0 see [http://www.apache.org/licenses/LICENSE-2.0.html](http://www.apache.org/licenses/LICENSE-2.0.html)