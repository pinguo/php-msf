<?php

/**
 * Rpc 客户端
 * 使用方式如：RpcClient::serv('user')->handler('mobile')->getByUid($context, $uid);
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Client;

use PG\Helper\SecurityHelper;
use PG\MSF\Server\CoreBase\SwooleException;
use PG\MSF\Server\Helpers\Context;

class RpcClient
{

    /**
     * 所有服务
     */
    private static $services;

    /**
     * @var string 当前版本
     */
    public $version = '0.9';

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
        list($root, ) = explode('.', $service);
        $commonConfig = getInstance()->config->get('params.service.' . $root, []);
        $config = getInstance()->config->get('params.service.' . $service, []);
        $config = array_merge($commonConfig, $config);
        if (empty($commonConfig) || empty($config)) {
            throw new SwooleException($service . ' service configuration not found.');
        }
        if (! isset($config['host'])) {
            throw new SwooleException('Host configuration not found.');
        }

        $this->useRpc = $config['useRpc'] ?? false;
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
            self::$services[$service] = new static($service);
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
    public function __call($method, array $args)
    {
        $context = $args[0];
        array_shift($args);
        if ($this->scheme == 'tcp') { // 使用 tcp 方式调用
            $response = yield self::remoteTcpCall($context, $method, $args, $this);
        } else {
            $response = yield self::remoteHttpCall($context, $method, $args, $this);
        }

        return $response;
    }

    /**
     * 生成用于非 rpc 调用的 url
     * @return string
     */
    protected function genUrl()
    {
        return $this->host . $this->urlPath;
    }

    /**
     * 非 Rpc 模式执行远程调用
     * @param string $url
     * @param mixed $args
     */
    public static function remoteTcpCall(Context $context, $method, array $args, RpcClient $rpc)
    {
        $reqParams = [
            'version' => $rpc->version,
            'args' => $args,
            'time' => microtime(true),
            'handler' => $rpc->handler,
            'method' => $method
        ];
        $reqParams['X-RPC'] = 1;
        $reqParams['sig'] = self::genSig($reqParams, $rpc->secret);

        $tcpClient = yield $context->tcpClient->coroutineGetTcpClient($rpc->host);
        $data = yield $tcpClient->coroutineSend(['path' => '/', 'data' => $reqParams]);

        return $data;
    }

    /**
     * Rpc 模式执行远程调用
     * @param string $pack_data 打包好的数据
     */
    public static function remoteHttpCall(Context $context, $method, array $args, RpcClient $rpc)
    {
        $headers = [];
        if ($rpc->useRpc) {
            $reqParams = [
                'version' => $rpc->version,
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

        $httpClient = yield $context->client->coroutineGetHttpClient($rpc->genUrl(), $rpc->timeout, $headers);
        if ($rpc->verb == 'GET') {
            $query = http_build_query($sendData);
            $data = yield $httpClient->coroutineGet($rpc->urlPath, $query, $rpc->timeout);
        } else {
            $data = yield $httpClient->coroutinePost($rpc->urlPath, $sendData, $rpc->timeout);
        }

        return $data;
    }

    public static function genSig($params, $secret)
    {
        $sig = SecurityHelper::sign($params, $secret);

        return $sig;
    }
}
