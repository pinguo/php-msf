<?php
/**
 * HTTP客户端
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Client\Http;

use PG\MSF\Base\Core;
use PG\MSF\Coroutine\Dns;
use PG\MSF\Coroutine\Http;
use PG\MSF\Client\Exception;

/**
 * Class Client
 * @package PG\MSF\Client\Http
 */
class Client extends Core
{
    /**
     * @var int DNS缓存有效时间（秒）
     */
    protected static $dnsExpire = 60;

    /**
     * @var int DNS缓存有效次数
     */
    protected static $dnsTimes = 10000;

    /**
     * @var array DNS查询缓存
     */
    public static $dnsCache = [];

    /**
     * @var int keep-alive有效时间(秒)
     */
    protected static $keepAliveExpire = 120;

    /**
     * @var int keep-alive有效次数
     */
    protected static $keepAliveTimes = 10000;

    /**
     * @var array http keep-alive cache HTTP Client长连接缓存
     */
    public static $keepAliveCache = [];

    /**
     * @var array 请求报头
     */
    public $headers;

    /**
     * @var \swoole_http_client swoole http client
     */
    public $client;

    /**
     * @var array 解析的URL结果
     */
    public $urlData = [];

    /**
     * @var int DNS解析超时时间
     */
    public $dnsTimeout = 30000;

    /**
     * Client constructor.
     *
     * @param string $url 如http://domain.com:port，http://domain.com:port/path/
     * @param int $timeout 域名解析超时时间，单位毫秒
     * @param array $headers 请求报头
     *
     * @return self
     *
     * @throws Exception 给定的url解析失败.
     */
    public function __construct($url = '', $timeout = 0, $headers = [])
    {
        if (!empty($headers)) {
            $this->setHeaders($headers);
        }

        if (!empty($url)) {
            $data = self::parseUrl($url);
            if ($data === false) {
                throw new Exception('url parse error, please check you url.');
            }
            $this->urlData = $data;
        }

        if ($timeout) {
            $this->dnsTimeout = $timeout;
        }

        self::$dnsExpire       = $this->getConfig()->get('http.dns.expire', 60);
        self::$dnsTimes        = $this->getConfig()->get('http.dns.times', 10000);
        self::$keepAliveExpire = $this->getConfig()->get('http.keepAlive.expire', 120);
        self::$keepAliveTimes  = $this->getConfig()->get('http.keepAlive.times', 10000);

        return $this;
    }

    /**
     * __call魔术方法
     *
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        return $this->client->{$name}(...$arguments);
    }

    /**
     * 设置请求报头
     *
     * @param array $headers 键值对应数组的报头列表
     * @return $this
     */
    public function setHeaders($headers)
    {
        if (!empty($this->headers)) {
            $this->headers = array_merge($this->headers, $headers);
        } else {
            $this->headers = $headers;
        }
        $this->headers = array_merge($this->headers, $headers);

        if ($this->client instanceof \swoole_http_client) {
            $this->client->setHeaders($this->headers);
        }
        return $this;
    }

    /**
     * 获取当前请求报头
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }


    /**
     * 设置请求Cookies
     *
     * @param array $cookies 键值对应数组的Cookie数据
     * @return $this
     */
    public function setCookies($cookies)
    {
        $this->client->setCookies($cookies);
        return $this;
    }

    /**
     * 异步DNS查询
     *
     * @param callable $callBack DNS查询异步回调函数
     * @param array $headers 键值对应数组的报头列表
     * @throws \Exception
     */
    public function asyncDnsLookup($callBack, array $headers = [])
    {
        $this->urlData['callBack'] = $callBack;
        $this->urlData['headers']  = $headers;

        $ip = self::getDnsCache($this->urlData['host']);
        if ($ip !== null) {
            $this->dnsLookupCallBack($ip);
        } else {
            $requestId = $this->getContext()->getRequestId();
            swoole_async_dns_lookup($this->urlData['host'], function ($host, $ip) use ($requestId) {
                if ($ip === '127.0.0.0') { // fix bug
                    $ip = '127.0.0.1';
                }

                if (empty(getInstance()->scheduler->taskMap[$requestId])) {
                    return;
                }

                if (empty($ip)) {
                    $this->getContext()->getLog()->warning($this->urlData['url'] . ' DNS查询失败');
                } else {
                    self::setDnsCache($host, $ip);
                    $this->dnsLookupCallBack($ip);
                }
            });
        }
    }

    /**
     * 运行DNS查询协程
     *
     * @param string $url  如 https://www.baidu.com
     * @param int $timeout 协程超时时间
     * @param array $headers 额外的报头
     * @return Dns|$this
     */
    public function goDnsLookup($url = '', $timeout = 0, $headers = [])
    {
        if (!empty($headers)) {
            $this->setHeaders($headers);
        }

        if (!empty($url)) {
            $this->urlData = self::parseUrl($url);
        }

        if ($timeout) {
            $this->dnsTimeout = $timeout;
        }

        $ip = Client::getDnsCache($this->urlData['host']);
        if ($ip !== null) {
            $this->client = $this->getHttpClient($this->urlData['host'], $ip, $this->urlData['port'], $this->urlData['ssl']);

            $headers = array_merge($headers, [
                'Host'        => $this->urlData['host'],
                'X-Ngx-LogId' => $this->getContext()->getLogId(),
                'Accept-Encoding' => 'gzip,deflate'
            ]);
            $this->setHeaders($headers);
            return $this;
        } else {
            return $this->getObject(Dns::class, [$this, $this->dnsTimeout, $headers]);
        }
    }

    /**
     * 在完成DNS查询的基础上，运行POST请求协程
     *
     * @param string $url 请求的URL
     * @param array $data POST的数据
     * @param int $timeout 请求超时时间
     * @param array $headers 额外的报头
     * @return Http
     * @throws Exception
     */
    public function goPost($url = '', $data = [], $timeout = 30000, $headers = [])
    {
        if (empty($data)) {
            throw new Exception('post data is empty');
        }

        if (!($this->client instanceof \swoole_http_client)) {
            throw new Exception('You must complete the DNS query first, Such as $client->goDnsLookup()');
        }

        if (empty($this->urlData)) {
            $this->urlData = self::parseUrl($url);
        } else {
            if (!empty($url)) {
                $this->urlData['path'] = $url;
            }
        }
        $this->setHeaders($headers);
        $sendPostReq  = $this->getObject(Http::class, [$this, 'POST', $this->urlData['path'], $data, $timeout]);

        return $sendPostReq;
    }

    /**
     * 在完成DNS查询的基础上，运行请求协程
     *
     * @param string $url 请求的URL
     * @param array $data 请求数据
     * @param int $timeout 请求超时时间
     * @param array $headers 额外的报头
     * @return Http
     * @throws Exception
     */
    public function goExecute($url = '', $data = [], $timeout = 30000, $headers = [])
    {
        if (!($this->client instanceof \swoole_http_client)) {
            throw new Exception('You must complete the DNS query first, Such as $client->goDnsLookup()');
        }

        if (empty($this->urlData)) {
            $this->urlData = self::parseUrl($url);
        } else {
            if (!empty($url)) {
                $this->urlData['path'] = $url;
            }
        }
        $this->setHeaders($headers);
        $sendPostReq  = $this->getObject(Http::class, [$this, 'EXECUTE', $this->urlData['path'], $data, $timeout]);

        return $sendPostReq;
    }

    /**
     * 在完成DNS查询的基础上，运行GET请求协程
     *
     * @param string $url 请求的URL
     * @param array $query POST的数据
     * @param int $timeout 请求超时时间
     * @param array $headers 额外的报头
     * @return Http
     * @throws Exception
     */
    public function goGet($url = '', $query = null, $timeout = 30000, $headers = [])
    {
        if (!($this->client instanceof \swoole_http_client)) {
            throw new Exception('You must complete the DNS query first, Such as $client->goDnsLookup()');
        }

        if (empty($this->urlData)) {
            $this->urlData = self::parseUrl($url);
        } else {
            if (!empty($url)) {
                $this->urlData['path'] = $url;
            }
        }
        $this->setHeaders($headers);

        //支持直接在url之后带参数形式的GET请求
        $q = '';
        if (!empty($this->urlData['query'])) {
            $q = ltrim($this->urlData['query'], '?');
        }

        if (!empty($query)) {
            $q .= (empty($q) ? '' : '&') . http_build_query($query) ;
        }

        $sendGetReq  = $this->getObject(Http::class, [$this, 'GET', $this->urlData['path'], $q, $timeout]);

        return $sendGetReq;
    }

    /**
     * 单个独立请求协程（自动完成DNS查询、获取数据）
     *
     * @param string $url 请求的URL
     * @param array $data 请求数据
     * @param int $timeout 请求超时时间
     * @param array $headers 额外的报头
     * @return Http
     * @throws Exception
     */
    public function goSingleExecute($url = '', $data = [], $timeout = 30000, $headers = [])
    {
        if (empty($this->urlData)) {
            $this->urlData = self::parseUrl($url);
        } else {
            if (!empty($url)) {
                $this->urlData['path'] = $url;
            }
        }

        yield $this->goDnsLookup();
        $this->setHeaders($headers);

        if (!empty($this->urlData['query'])) {
            return yield $this->getObject(
                Http::class,
                [$this, 'EXECUTE', $this->urlData['path'] . $this->urlData['query'], $data, $timeout]
            );
        } else {
            return yield $this->getObject(
                Http::class,
                [$this, 'EXECUTE', $this->urlData['path'], $data, $timeout]
            );
        }
    }

    /**
     * 单个独立POST请求协程（自动完成DNS查询、获取数据）
     *
     * @param string $url 请求的URL
     * @param array $data POST的数据
     * @param int $timeout 请求超时时间
     * @param array $headers 额外的报头
     * @return Http
     * @throws Exception
     */
    public function goSinglePost($url = '', $data = [], $timeout = 30000, $headers = [])
    {
        if (empty($data)) {
            throw new Exception('post data is empty');
        }

        if (empty($this->urlData)) {
            $this->urlData = self::parseUrl($url);
        } else {
            if (!empty($url)) {
                $this->urlData['path'] = $url;
            }
        }

        yield $this->goDnsLookup();
        $this->setHeaders($headers);

        if (!empty($this->urlData['query'])) {
            return yield $this->getObject(
                Http::class,
                [$this, 'POST', $this->urlData['path'] . $this->urlData['query'], $data, $timeout]
            );
        } else {
            return yield $this->getObject(
                Http::class,
                [$this, 'POST', $this->urlData['path'], $data, $timeout]
            );
        }
    }

    /**
     * 单个独立GET请求协程（自动完成DNS查询、获取数据）
     *
     * @param string $url 请求的URL
     * @param array $query GET的数据
     * @param int $timeout 请求超时时间
     * @param array $headers 额外的报头
     * @return Http
     * @throws Exception
     */
    public function goSingleGet($url = '', $query = [], $timeout = 30000, $headers = [])
    {
        if (empty($this->urlData)) {
            $this->urlData = self::parseUrl($url);
        } else {
            if (!empty($url)) {
                $this->urlData['path'] = $url;
            }
        }

        yield $this->goDnsLookup();
        $this->setHeaders($headers);

        //支持直接在url之后带参数形式的GET请求
        $q = '';
        if (!empty($this->urlData['query'])) {
            $q = ltrim($this->urlData['query'], '?');
        }

        if (!empty($query)) {
            $q .= (empty($q) ? '' : '&') . http_build_query($query) ;
        }

        return yield $this->getObject(Http::class, [$this, 'GET', $this->urlData['path'], $q, $timeout]);
    }

    /**
     * 并行POST或者Get请求协程（自动完成DNS查询、获取数据）
     *
     * @param array $requests 格式如：
     *   [
     *       [
     *           'url'         => 'http://www.baidu.com/xxx', // 必须为全路径URL
     *           'method'      => 'GET', // 默认GET
     *           'dns_timeout' => 1000, // 默认为30s
     *           'timeout'     => 3000,  // 默认不超时
     *           'headers'     => [],    // 默认为空
     *           'data'        => ['a' => 'b'] // 发送数据
     *       ],
     *       [
     *           'url'         => 'http://www.baidu.com/xxx',
     *           'method'      => 'POST',
     *           'timeout'     => 3000,
     *           'headers'     => [],
     *           'data'        => ['a' => 'b'] // 发送数据
     *       ],
     *       [
     *           'url'         => 'http://www.baidu.com/xxx',
     *           'method'      => 'POST',
     *           'timeout'     => 3000,
     *           'headers'     => [],
     *           'data'        => ['a' => 'b'] // 发送数据
     *       ],
     *   ];
     *
     * @return array
     * @throws Exception
     */
    public function goConcurrent(array $requests)
    {
        if (empty($requests)) {
            throw new Exception('$requests is empty');
        }

        $go               = [];
        $dns              = [];
        $result           = [];
        $sendHttpRequests = [];
        // 格式化请求参数，并运行DNS查询协程
        foreach ($requests as $key => $request) {
            if (is_array($request)) {
                if (empty($request['url'])) {
                    unset($requests[$key]);
                    $this->getContext()->getLog()->error('$requests[' . $key . '] has not url field');
                    continue;
                }

                $url = $request['url'];
                if (isset($request['dns_timeout'])) {
                    $dnsTimeout = $request['dns_timeout'];
                } else {
                    $dnsTimeout = $this->dnsTimeout;
                }

                if (isset($request['headers'])) {
                    $headers = $request['headers'];
                } else {
                    $headers    = [];
                }

                if (!isset($request['method'])) {
                    $requests[$key]['method'] = 'GET';
                } else {
                    $method = strtoupper($request['method']);
                    if ($method != 'GET' && $method != 'POST') {
                        unset($requests[$key]);
                        $this->getContext()->getLog()->error('$requests[' . $key . '] method field not valid, must be GET or POST, And Case Sensitive');
                        continue;
                    }

                    if ($method == 'POST' && empty($request['data'])) {
                        $this->getContext()->getLog()->error('$requests[' . $key . '] post data is empty');
                        continue;
                    }
                }

                if (!isset($request['timeout'])) {
                    $requests[$key]['timeout'] = 0;
                }

                if (!isset($request['data'])) {
                    $requests[$key]['data'] = [];
                }

                $requests[$key]['dns_timeout'] = $dnsTimeout;
                $requests[$key]['headers']     = $headers;
            } else {
                $url              = $request;
                $dnsTimeout       = $this->dnsTimeout;
                $headers          = [];
                $requests[$key]   = [
                    'url'         => $url,
                    'method'      => 'GET',
                    'dns_timeout' => $dnsTimeout,
                    'headers'     => $headers,
                    'timeout'     => 0,
                    'data'        => [],

                ];
            }

            /**
             * @var Client[] $go
             */
            $go[$key]  = $this->getObject(self::class);
            /**
             * @var Dns[] $dns
             */
            $dns[$key] = $go[$key]->goDnsLookup($url, $dnsTimeout, $headers);
        }

        // 获取DNS查询结果
        foreach ($dns as $key => $goDns) {
            if ($goDns instanceof Client) {
                continue;
            }

            if (!yield $goDns) {
                $this->getContext()->getLog()->error('DNS lookup for ' . $requests[$key]['url'] . ' Failed');
                $result[$key] = false;
                continue;
            }
        }

        // 运行HTTP请求协程
        foreach ($go as $key => $client) {
            if (isset($result[$key])) {
                continue;
            }

            if ($requests[$key]['method'] == 'GET') {
                if (is_array($requests[$key]['data'])) {
                    $requests[$key]['data'] = http_build_query($requests[$key]['data']);
                }
                $sendHttpRequests[$key] = $this->getObject(Http::class, [$client, 'GET', $client->urlData['path'], $requests[$key]['data'], $requests[$key]['timeout']]);
            }

            if ($requests[$key]['method'] == 'POST') {
                $sendHttpRequests[$key] = $this->getObject(Http::class, [$client, 'POST', $client->urlData['path'], $requests[$key]['data'], $requests[$key]['timeout']]);
            }
        }

        // 获取HTTP请求结果
        foreach ($sendHttpRequests as $key => $httpRequest) {
            $result[$key] = yield $httpRequest;
        }

        return $result;
    }

    /**
     * DNS查询返回时回调
     *
     * @param $ip string 主机名对应的IP
     * @return bool
     */
    public function dnsLookupCallBack($ip)
    {
        if (empty($this->context) || empty($this->context->getLog())) {
            return true;
        }

        // swoole_http_client手工析构有Segmentation fault，暂时直接new
        //$this->client = $this->getObject(\swoole_http_client::class, [$ip, $this->urlData['port'], $this->urlData['ssl']]);
        $this->client = $this->getHttpClient($this->urlData['host'], $ip, $this->urlData['port'], $this->urlData['ssl']);

        $headers = array_merge($this->urlData['headers'], [
            'Host' => $this->urlData['host'],
            'X-Ngx-LogId' => $this->context->getLogId(),
            'Accept-Encoding' => 'gzip,deflate'
        ]);

        $this->setHeaders($headers);
        ($this->urlData['callBack'])($this);
    }

    /**
     * 标准化解析URL
     *
     * @param string $url 待解析URL
     * @return bool|mixed
     */
    public static function parseUrl($url)
    {
        $parseUrlResult = parse_url($url);
        if ($parseUrlResult === false) {
            return false;
        }

        if (empty($parseUrlResult['scheme'])) {
            $parseUrlResult['scheme'] = 'http';
        }

        if (empty($parseUrlResult['host'])) {
            return false;
        }

        $parseUrlResult['url'] = $url;

        if (empty($parseUrlResult['port'])) {
            if ($parseUrlResult['scheme'] == 'http') {
                $parseUrlResult['port'] = 80;
                $parseUrlResult['ssl']  = false;
            } else {
                $parseUrlResult['port'] = 443;
                $parseUrlResult['ssl']  = true;
            }
        } else {
            if ($parseUrlResult['scheme'] == 'http') {
                $parseUrlResult['ssl']  = false;
            } else {
                $parseUrlResult['ssl']  = true;
            }
        }

        if (empty($parseUrlResult['path'])) {
            $parseUrlResult['path'] = '/';
        }

        if (empty($parseUrlResult['query'])) {
            $parseUrlResult['query'] = '';
        } else {
            $parseUrlResult['query'] = '?' . $parseUrlResult['query'];
        }

        return $parseUrlResult;
    }

    /**
     * 发起异步GET请求
     *
     * @param string $path 待请求的URL Path
     * @param string $query 查询参数
     * @param $callback
     */
    public function get($path, string $query, $callback)
    {
        if (!empty($query)) {
            $path = $path . '?' . $query;
        }
        $this->client->get($path, $callback);
    }

    /**
     * 发起异步POST请求
     *
     * @param string $path 待请求的URL Path
     * @param array $data 发送的POST数据
     * @param $callback
     */
    public function post($path, $data, $callback)
    {
        // 解决swoole底层http_build_query failed的问题
        if (empty($data)) {
            $data = '';
        }
        $this->client->post($path, $data, $callback);
    }


    /**
     * 发起异步请求
     *
     * @param string $path 待请求的URL Path
     * @param $callback
     */
    public function execute($path, $callback)
    {
        $this->client->execute($path, $callback);
    }

    /**
     * 设置DNS缓存
     *
     * @param string $host HOST名称
     * @param string $ip 已解析的IP
     */
    public static function setDnsCache($host, $ip)
    {
        self::$dnsCache[$host] = [
            $ip, time(), 1
        ];
    }

    /**
     * 清除HOST对应的DNS缓存
     *
     * @param string $host HOST名称
     */
    public static function clearDnsCache($host)
    {
        unset(self::$dnsCache[$host]);
    }

    /**
     * 获取DNS缓存
     *
     * @param string $host HOST名称
     * @return mixed|null
     */
    public static function getDnsCache($host)
    {
        if (!empty(self::$dnsCache[$host])) {
            if (time() - self::$dnsCache[$host][1] > self::$dnsExpire) {
                return null;
            }

            if (self::$dnsCache[$host][2] > self::$dnsTimes) {
                return null;
            }

            self::$dnsCache[$host][2]++;
            return self::$dnsCache[$host][0];
        } else {
            return null;
        }
    }

    /**
     * @param string $domain
     * @param string $ip
     * @param int $port
     * @param $ssl
     * @return mixed|null|\swoole_http_client
     */
    private function getHttpClient(string $domain, string $ip, int $port, $ssl)
    {
        $client = null;
        if (self::$keepAliveExpire) {
            $k = $domain . '|' . $ip . '|' . $port;
            //是否创建新的http_client对象
            $isCreate = true;
            //存在队列
            if (isset(self::$keepAliveCache[$k])) {
                //队列里有http_client对象
                while (!(self::$keepAliveCache[$k])->isEmpty() && $client = (self::$keepAliveCache[$k])->dequeue()) {
                    //http_client对象有效
                    if ($client->isConnected()) {
                        $client->useCount++;
                        $isCreate = false;
                        break;
                    } else {
                        $client = null;
                    }
                }
            } else {
                //不存在队列
                self::$keepAliveCache[$k] = new \SplQueue();
            }

            //新建http_client对象
            if ($isCreate) {
                $client = new \swoole_http_client($ip, $port, $ssl);
                $client->set(['timeout' => -1, 'keep_alive' => 1]);
                $client->useCount = 1;
                $client->genTime  = time();
                $client->isClose  = false;
            }
        } else {
            $client = new \swoole_http_client($ip, $port, $ssl);
            $client->set(['timeout' => -1]);
        }
        $client->ioBack = false;

        return $client;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
        if ($this->client instanceof \swoole_http_client) {
            if (isset($this->client->headers['connection']) &&
                strlen($this->client->headers['connection']) === 10 && // 表示 Keep-Alive 长度为10
                $this->client->ioBack  == true && $this->client->isClose == false &&
                (time() - $this->client->genTime) < self::$keepAliveExpire &&
                $this->client->useCount < self::$keepAliveTimes
            ) {
                //回收长连接
                $k = $this->client->requestHeaders['Host'] . '|' . $this->client->host . '|' . $this->client->port;
                (self::$keepAliveCache[$k])->enqueue($this->client);
            } else {
                //关闭连接
                $this->client->close();
            }
        }
    }
}
