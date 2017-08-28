<?php
/**
 * RPC客户端
 *
 * 请求单个RPC服务
 * $user  = yield $this->getObject(RpcClient::class, ['user'])->handler('mobile', $construct)->getByUid($uid);
 *
 * 批量请求多个RPC服务
 * $rpc[] = $this->getObject(RpcClient::class, ['user'])->handler('mobile', $construct)->func('getByUid', $uid);
 * $rpc[] = $this->getObject(RpcClient::class, ['user'])->handler('mobile', $construct)->func('getByName', $name);
 * $rpc[] = $this->getObject(RpcClient::class, ['user'])->handler('mobile', $construct)->func('getByEmail', $email);
 * $users = yield RpcClient::goConcurrent($rpc);
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Client;

use Exception;
use PG\MSF\Client\Http\Client;
use PG\MSF\Base\Core;

/**
 * Class RpcClient
 * @package PG\MSF\Client
 */
class RpcClient extends Core
{
    /**
     * @var string 当前版本
     */
    public static $version = '0.9';

    /**
     * @var array 所有服务
     */
    protected static $services;

    /**
     * @var string 服务名称
     */
    public $service = '';

    /**
     * @var string host地址，支持 http/https 协议，支持域名地址，支持带端口.
     * http://127.0.0.1 | http://hostname | http://127.0.0.1:80 | http://hostname:80
     * https://127.0.0.1 | https://hostname | https://127.0.0.1:443 | https://hostname:443
     */
    public $host = 'http://127.0.0.1';

    /**
     * @var int 超时时间(单位毫秒)
     */
    public $timeout = 0;

    /**
     * @var string 使用协议，http
     */
    public $scheme = 'http';

    /**
     * @var string url path，如 /path，当不使用rpc模式时，应设置本参数；
     */
    public $urlPath = 'Rpc';

    /**
     * @var string 动作，如GET/POST
     */
    public $verb = 'POST';

    /**
     * @var string handler名称
     */
    public $handler = '';

    /**
     * @var string handler的构造参数
     */
    public $construct = '';

    /**
     * @var string handler的方法名
     */
    public $method = '';

    /**
     * @var array handler的方法参数
     */
    public $args = [];

    /**
     * RpcClient constructor.
     *
     * @param string $service 服务名称，如 'user'
     * @throws Exception
     */
    public function __construct($service)
    {
        $this->service = $service;
        if (isset(static::$services[$service])) {
            // 赋值到类属性中.
            $this->host    = static::$services[$service]['host'];
            $this->verb    = static::$services[$service]['verb'];
            $this->timeout = static::$services[$service]['timeout'];
        } else {
            // 获得配置信息
            /**
             * 'user' = [
             *     'host' => 'http://10.1.90.10:80', <必须>
             *     'timeout' => 1000, <选填，可被下级覆盖>
             * ]
             */
            $config = getInstance()->config->get('params.service.' . $service, []);
            list($root,) = explode('.', $service);
            $config['host'] = getInstance()->config->get('params.service.' . $root . '.host', '');
            if ($config['host'] === '') {
                throw new Exception('Host configuration not found.');
            }

            if (!isset($config['timeout'])) {
                $config['timeout'] = getInstance()->config->get('params.service.' . $root . '.timeout', 0);
            }

            // 赋值到类属性中.
            $this->host    = $config['host'];
            $scheme = substr($this->host, 0, strpos($this->host, ':'));
            if (!in_array($scheme, ['http', 'https'])) {
                throw new Exception('Host configuration invalid.');
            }
            $this->verb = $config['verb'] ?? 'POST';
            $this->timeout = $config['timeout'];

            static::$services[$service]['host']    = $this->host;
            static::$services[$service]['verb']    = $this->verb;
            static::$services[$service]['timeout'] = $this->timeout;
        }
    }

    /**
     * 指定服务句柄，一般为RPC服务导出的类名
     *
     * @param string $handler 服务句柄（类名）
     * @param array|null 服务句柄的构造参数
     * @return RpcClient
     */
    public function handler($handler, $construct = null)
    {
        $this->handler   = $handler;
        $this->construct = $construct;
        return $this;
    }

    /**
     * 拼装handler执行的方法和参数
     *
     * @param string $method handler执行的方法
     * @param array $args handler执行的参数
     * @return $this
     */
    public function func($method, ...$args)
    {
        $this->method = $method;
        $this->args   = $args;
        return $this;
    }

    /**
     * 指定一个远程服务句柄的方法
     *
     * @param string $method 方法名
     * @param mixed $args 执行的参数列表
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        $this->method = $method;
        $this->args   = $args;
        $response     = yield $this->remoteHttpCall($method, $args);

        return $response;
    }

    /**
     * Rpc模式执行远程调用
     *
     * @param string $method 远程服务句柄的方法名
     * @param array $args 执行的参数列表
     * @return mixed
     * @throws Exception
     */
    public function remoteHttpCall($method, array $args)
    {
        $reqParams = [
            'version'   => static::$version,
            'args'      => array_values($args),
            'time'      => microtime(true),
            'handler'   => $this->handler,
            'construct' => $this->construct,
            'method'    => $method
        ];
        $sendData = [
            'data' => getInstance()->pack->pack($reqParams),
        ];
        $headers = [
            'X-RPC' => 1,
        ];

        /**
         * @var Client $client
         */
        $client = yield $this->getObject(Client::class)->goDnsLookup($this->host, $this->timeout, $headers);
        if (!$client) {
            throw new Exception($this->host . ' dns query failed');
        }

        $urlPath = '/' .$this->urlPath . '/' . $this->service . '/' . $this->handler . '/' . $this->method;
        if ($this->verb == 'POST') {
            $response = yield $client->goPost($urlPath, $sendData, $this->timeout);
        } else {
            $response = yield $client->goGet($urlPath, $sendData, $this->timeout);
        }

        if ($response && !empty($response['body']) && ($jsonRes = json_decode($response['body'], true))) {
            if ($jsonRes['status'] !== 200) {
                $this->getContext()->getLog()->warning(dump($response, false, true));
                return false;
            } else {
                return $jsonRes['data'];
            }
        } else {
            $this->getContext()->getLog()->warning(dump($response, false, true));
            return false;
        }
    }

    /**
     * 多个RPC并行请求
     *
     * @param array $multiRpc RpcClient实例列表
     * @return array
     */
    public static function goConcurrent(array $multiRpc)
    {
        $results  = [];
        $requests = [];
        $context  = null;

        foreach ($multiRpc as $key => $rpc) {
            if (!($rpc instanceof RpcClient)) {
                $results[$key] = false;
            }

            $reqParams = [
                'version'   => static::$version,
                'args'      => array_values($rpc->args),
                'time'      => microtime(true),
                'handler'   => $rpc->handler,
                'construct' => $rpc->construct,
                'method'    => $rpc->method,
            ];
            $sendData = [
                'data' => getInstance()->pack->pack($reqParams),
            ];

            $requests[$key]['url']         = $rpc->host . '/' . $rpc->urlPath . '/' . $rpc->service . '/' . $rpc->handler . '/' . $rpc->method;
            $requests[$key]['method']      = $rpc->verb;
            $requests[$key]['dns_timeout'] = 2000;
            $requests[$key]['timeout']     = $rpc->timeout;
            $requests[$key]['headers']     = ['X-RPC' => 1];
            $requests[$key]['data']        = $sendData;
            $context                       = $context ?? $rpc->getContext();
        }

        if (empty($requests)) {
            return $results;
        }

        $responses = yield $context->getObject(Client::class)->goConcurrent($requests);
        foreach ($responses as $key => $response) {
            if ($response && !empty($response['body']) && ($jsonRes = json_decode($response['body'], true))) {
                if ($jsonRes['status'] !== 200) {
                    $results[$key] = false;
                    $context->getContext()->getLog()->warning(dump($response, false, true));
                } else {
                    $results[$key] = $jsonRes['data'];
                }
            } else {
                $results[$key] = false;
                $context->getContext()->getLog()->warning(dump($response, false, true));
            }
        }

        return $results;
    }
}
