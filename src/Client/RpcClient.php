<?php
/**
 * RPC客户端
 *
 * 请求单个RPC服务
 * $user  = yield $this->getObject(RpcClient::class)->serv('user')->handler('mobile', $construct)->getByUid($uid);
 *
 * 批量请求多个RPC服务
 * $rpc[] = $this->getObject(RpcClient::class)->serv('user')->handler('mobile', $construct)->func('getByUid', $uid);
 * $rpc[] = $this->getObject(RpcClient::class)->serv('user')->handler('mobile', $construct)->func('getByName', $name);
 * $rpc[] = $this->getObject(RpcClient::class)->serv('user')->handler('mobile', $construct)->func('getByEmail', $email);
 * $users = yield RpcClient::goConcurrent($rpc);
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */
namespace PG\MSF\Client;

use Exception;
use PG\MSF\Client\Http\Client;
use PG\MSF\Base\Core;

class RpcClient extends Core
{
    /**
     * @var string 当前版本
     */
    public static $version = '0.9';

    /**
     * @var array json 错误码
     */
    protected static $jsonErrors = [
        JSON_ERROR_NONE           => null,
        JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
        JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
        JSON_ERROR_CTRL_CHAR      => 'Unexpected control character found',
        JSON_ERROR_SYNTAX         => 'Syntax error, malformed JSON',
        JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    ];

    /**
     * @var array 所有服务
     */
    protected static $services;

    /**
     * @var string host地址，支持 http/https 协议，支持域名地址，支持带端口.
     * http://127.0.0.1 | http://hostname | http://127.0.0.1:80 | http://hostname:80
     * https://127.0.0.1 | https://hostname | https://127.0.0.1:443 | https://hostname:443
     */
    protected $host = 'http://127.0.0.1';

    /**
     * @var int 超时时间(单位毫秒)
     */
    protected $timeout = 0;

    /**
     * @var bool 调用是否使用rpc方式，为了兼容还未rpc改造的内部服务，已经支持rpc调用的服务，可设置为false，默认为true
     */
    protected $useRpc = true;

    /**
     * @var string 使用协议，http
     */
    protected $scheme = 'http';

    /**
     * @var string url path，如 /path，当不使用rpc模式时，应设置本参数；
     */
    protected $urlPath = '/';

    /**
     * @var string 动作，如GET/POST
     */
    protected $verb = 'POST';

    /**
     * @var string handler名称
     */
    protected $handler = '';

    /**
     * @var string handler的构造参数
     */
    protected $construct = '';

    /**
     * @var string handler的方法名
     */
    protected $method = '';

    /**
     * @var array handler的方法参数
     */
    protected $args = [];

    /**
     * RpcClient constructor.
     *
     * @param string $service 服务名称，如 'user'
     * @throws Exception
     */
    public function __construct($service)
    {
        if (isset(static::$services[$service])) {
            // 赋值到类属性中.
            $this->host    = static::$services[$service]['host'];
            $this->useRpc  = static::$services[$service]['useRpc'];
            $this->urlPath = static::$services[$service]['urlPath'];
            $this->verb    = static::$services[$service]['verb'];
            $this->timeout = static::$services[$service]['timeout'];
        } else {
            // 获得配置信息
            /**
             * 'user' = [
             *     'host' => 'http://10.1.90.10:80', <必须>
             *     'timeout' => 1000, <选填，可被下级覆盖>
             *     'use_rpc' => true, <选填，可被下级覆盖>
             *     '1' => [
             *         'use_rpc' => false, // 是否真的使用rpc方式，为了兼容非rpc服务 <选填，如果填写将会覆盖上级的use_rpc>
             *         'url_path' => '/path', <选填>
             *         'verb' => 'GET', <选填>
             *         'timeout' => 2000, <选填，如果填写将会覆盖上级的 timeout>
             *     ]
             * ]
             */
            $config = getInstance()->config->get('params.service.' . $service, []);
            list($root,) = explode('.', $service);
            $config['host'] = getInstance()->config->get('params.service.' . $root . '.host', '');
            if ($config['host'] === '') {
                throw new Exception('Host configuration not found.');
            }
            if (!isset($config['use_rpc'])) {
                $config['use_rpc'] = getInstance()->config->get('params.service.' . $root . '.use_rpc', true);
            }
            if (!isset($config['timeout'])) {
                $config['timeout'] = getInstance()->config->get('params.service.' . $root . '.timeout', 0);
            }

            // 赋值到类属性中.
            $this->host = $config['host'];
            $this->useRpc = $config['useRpc'] ?? false;
            $this->urlPath = $config['urlPath'] ?? '/';
            $scheme = substr($this->host, 0, strpos($this->host, ':'));
            if (!in_array($scheme, ['http', 'https'])) {
                throw new Exception('Host configuration invalid.');
            }
            $this->verb = $config['verb'] ?? 'POST';
            $this->timeout = $config['timeout'];

            static::$services[$service]['host']    = $this->host;
            static::$services[$service]['useRpc']  = $this->useRpc;
            static::$services[$service]['urlPath'] = $this->urlPath;
            static::$services[$service]['verb']    = $this->verb;
            static::$services[$service]['timeout'] = $this->timeout;
        }
    }

    /**
     * 指定服务句柄，一般为控制器
     *
     * @param string $handler
     * @param array|null
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
     * 指定一个远程方法，如控制器名
     *
     * @param string $method
     * @param mixed $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        $this->method = $method;
        $this->args   = $args;
        $response     = $this->remoteHttpCall($method, $args);

        return $response;
    }

    /**
     * Rpc模式执行远程调用
     *
     * @param $method
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    public function remoteHttpCall($method, array $args)
    {
        $headers = [];
        if ($this->useRpc) {
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
        } else {
            $preParams = array_filter([
                '__appVersion' => !empty($this->getContext()->getInput()->postGet('__appVersion'))
                    ? $this->getContext()->getInput()->postGet('__appVersion')
                    : $this->getContext()->getInput()->postGet('appVersion'),
                '__locale' => !empty($this->getContext()->getInput()->postGet('__locale'))
                    ? $this->getContext()->getInput()->postGet('__locale')
                    : $this->getContext()->getInput()->postGet('locale'),
                '__platform' => !empty($this->getContext()->getInput()->postGet('__platform'))
                    ? $this->getContext()->getInput()->postGet('__platform')
                    : $this->getContext()->getInput()->postGet('platform')
            ]);
            $sendData = array_merge($args[0], $preParams);
        }

        /**
         * @var Client $client
         */
        $client = yield $this->getContext()->getObjectPool()->get(Client::class)->goDnsLookup($this->host, $this->timeout, $headers);
        if (!$client) {
            throw new Exception($this->host . ' dns query failed');
        }

        if ($this->verb == 'POST') {
            $response = yield $client->goPost($this->urlPath, $sendData, $this->timeout);
        } else {
            $response = yield $client->goGet($this->urlPath, $sendData, $this->timeout);
        }

        if ($response && !empty($response['body']) && ($jsonRes = json_decode($response['body']['data']))) {
            return $jsonRes;
        } else {
            $this->getContext()->getLog()->warning(dump($response, false, true));
            return false;
        }
    }

    /**
     * 多个RPC并行请求
     *
     * @param array $multiRpc
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

            $requests[$key]['url']         = $rpc->host . $rpc->urlPath;
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

        $responses = yield $context->getContext()->getObjectPool()->get(Client::class)->goConcurrent($requests);
        foreach ($responses as $key => $response) {
            if ($response && !empty($response['body']) && ($jsonRes = json_decode($response['body']['data']))) {
                $results[$key] = $jsonRes;
            } else {
                $results[$key] = false;
                $context->getContext()->getLog()->warning(dump($response, false, true));
            }
        }

        return $results;
    }
}
