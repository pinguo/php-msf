<?php

/**
 * Rpc 客户端
 * 使用方式如：RpcClient::serv('user')->handler('mobile')->getByUid($this, $uid);
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Client;

use PG\Helper\SecurityHelper;
use PG\MSF\Server\CoreBase\CoreBase;
use PG\MSF\Server\CoreBase\SwooleException;

class RpcClient
{

    /**
     * 所有服务
     */
    private static $services;

    /**
     * @var string 当前版本
     */
    public static $version = '0.9';

    /**
     * @var int 超时时间(单位毫秒)
     */
    public $timeout = 0;

    /**
     * 调用是否使用 rpc 方式，为了兼容还未 rpc 改造的内部服务；
     * 已经支持 rpc 调用的服务，可设置为 true，默认为 false
     */
    protected $useRpc = false;

    /**
     * @var string 使用协议，http/tcp
     */
    protected $scheme = 'http';

    /**
     * @var string host地址，支持 http/https/tcp 协议，支持域名地址，支持带端口.
     * eg:
     * http://127.0.0.1 | http://hostname | http://127.0.0.1:80 | http://hostname:80
     * https://127.0.0.1 | https://hostname | https://127.0.0.1:443 | https://hostname:443
     * tcp://127.0.0.1 | tcp://hostname | tcp://127.0.0.1:8080 | tcp://hostname:8080
     */
    protected $host = 'http://127.0.0.1';

    /**
     * @var null url path，如 /path，当不使用 rpc 模式时，应设置本参数；
     */
    protected $urlPath = null;

    /**
     * @var string 动作，如 GET/POST
     */
    protected $verb = 'POST';

    /**
     * @var string 服务名称
     */
    protected $service = null;

    /**
     * @var string 服务加密秘钥
     */
    protected $secret = null;

    /**
     * @var string 服务接收句柄，一般为控制器名称
     */
    protected $handler = null;

    public function __construct($service)
    {
        $this->service = $service;
        // 获得配置信息
        /**
         * 'user' = [
         *     'host' => 'http://10.1.90.10:80',
         *     'useRpc' => false, // 是否真的使用 rpc 方式，为了兼容非 rpc 服务
         *     'urlPath' => '/path',
         *     'secret' => 'xxxxxx',
         * ]
         */
        $config = getInstance()->config->get('params.service.' . $service, []);
        list($root,) = explode('.', $service);
        $config['host'] = getInstance()->config->get('params.service.' . $root . '.host', '');
        $config['timeout'] = getInstance()->config->get('params.service.' . $root . '.timeout', 0);
        $config['secret'] = getInstance()->config->get('params.service.' . $root . '.secret', 0);
        if (empty($config)) {
            throw new SwooleException($service . ' service configuration not found.');
        }
        if (! isset($config['host'])) {
            throw new SwooleException('Host configuration not found.');
        }

        $this->useRpc = $config['useRpc'] ?? false;
        $this->urlPath = $config['urlPath'] ?? null;
        if (isset($config['host'])) {
            $this->host = $config['host'];
            $scheme = substr($this->host, 0, strpos($this->host, ':'));
            if (! in_array($scheme, ['http', 'https', 'tcp'])) {
                throw new SwooleException('Host configuration invalid.');
            }
        }
        // 非 Rpc 模式
        if (! $this->useRpc) {
            if ($this->scheme === 'tcp') {
                throw new SwooleException('Non-rpc mode does not support tcp scheme.');
            }
            if ($this->urlPath === null) {
                throw new SwooleException('Need to set urlpath when not using rpc mode.');
            }
        }
        $this->verb = $config['verb'] ?? 'POST';
        $this->timeout = $config['timeout'] ?? 0;
        $this->secret = $config['secret'] ?? '';
    }

    /**
     * 指定服务名称，如 user
     * @param string $service
     * @return $this
     */
    public static function serv($service)
    {
        if (! isset(self::$services[$service])) {
            self::$services[$service] = new RpcClient($service);
        }

        return self::$services[$service];
    }

    /**
     * 指定服务句柄，一般为控制器
     * @param string $handler
     * @return $this
     */
    public function handler($handler)
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * 指定一个远程方法，如控制器名
     * @param string $method
     * @param mixed $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        if (! is_object($args[0]) || ! ($args[0] instanceof CoreBase)) {
            throw new SwooleException('The first argument of ' . $method . ' must be instanceof CoreBase .');
        }
        $obj = $args[0];
        array_shift($args);
        if ($this->scheme == 'tcp') { // 使用 tcp 方式调用
            $response = self::remoteTcpCall($obj, $method, $args, $this);
        } else {
            $response = self::remoteHttpCall($obj, $method, $args, $this);
        }

        return $response;
    }

    /**
     * 非 Rpc 模式执行远程调用
     * @param string $url
     * @param mixed $args
     */
    public static function remoteTcpCall(CoreBase $obj, $method, array $args, RpcClient $rpc)
    {
        $reqParams = [
            'version' => self::$version,
            'args' => $args,
            'time' => microtime(true),
            'handler' => $rpc->handler,
            'method' => $method
        ];
        $reqParams['X-RPC'] = 1;
        $reqParams['sig'] = self::genSig($reqParams, $rpc->secret);

        $tcpClient = yield $obj->tcpClient->coroutineGetTcpClient($rpc->host);
        $response = yield $tcpClient->coroutineSend(['path' => '/', 'data' => $reqParams]);

        return $response;
    }

    /**
     * Rpc 模式执行远程调用
     * @param string $pack_data 打包好的数据
     */
    public static function remoteHttpCall(CoreBase $obj, $method, array $args, RpcClient $rpc)
    {
        $headers = [];
        if ($rpc->useRpc) {
            $reqParams = [
                'version' => self::$version,
                'args' => $args,
                'time' => microtime(true),
                'handler' => $rpc->handler,
                'method' => $method
            ];
            $sendData = [
                'data' => getInstance()->pack->pack($reqParams),
                'sig' => self::genSig($reqParams, $rpc->secret),
            ];
            $headers = [
                'X-RPC' => 1,
            ];
        } else {
            $sendData = $args;
            $sendData['sig'] = self::genSig($args, $rpc->secret);
        }

        $httpClient = yield $obj->client->coroutineGetHttpClient($rpc->host, $rpc->timeout, $headers);
        if ($rpc->verb == 'GET') {
            $response = yield $httpClient->coroutineGet($rpc->urlPath, $sendData, $rpc->timeout);
        } else {
            $response = yield $httpClient->coroutinePost($rpc->urlPath, $sendData, $rpc->timeout);
        }
        if (! isset($response['body'])) {
            throw new SwooleException('The response of body is not found');
        }

        return json_decode($response['body'], true);
    }

    public static function genSig($params, $secret)
    {
        $sig = SecurityHelper::sign($params, $secret);

        return $sig;
    }
}
