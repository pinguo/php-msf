<?php
/**
 * 并行的兼容Flex标准的HTTP客户端
 *
 * 不推荐使用将来可能废弃，建议使用\PG\MSF\Client\Http\Client::goConcurrent($requests)
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Client;

use PG\Exception\BusinessException;
use PG\MSF\Base\Core;
use PG\MSF\Client\Http\Client;
use PG\MSF\Coroutine\Dns;

/**
 * Class ConcurrentClient
 * @package PG\MSF\Client
 */
class ConcurrentClient
{
    /**
     * @var array json errors
     */
    protected static $jsonErrors = [
        JSON_ERROR_NONE => null,
        JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
        JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
        JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
        JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
        JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    ];

    /**
     * 并行请求
     *
     * @param array $requests 请求的数据
     * @param Core $parent Core实例（通常为Controller实例）
     * @return array
     */
    public static function request(array $requests, Core $parent)
    {
        $config = $parent->getConfig();
        $serviceConf = $config->get('params.service', null);
        $parallelConf = $config->get('params.parallel');

        $preParams = array_filter([
            '__appVersion' => !empty($parent->getContext()->getInput()->postGet('__appVersion')) ? $parent->getContext()->getInput()->postGet('__appVersion') : $parent->getContext()->getInput()->postGet('appVersion'),
            '__locale' => !empty($parent->getContext()->getInput()->postGet('__locale')) ? $parent->getContext()->getInput()->postGet('__locale') : $parent->getContext()->getInput()->postGet('locale'),
            '__platform' => !empty($parent->getContext()->getInput()->postGet('__platform')) ? $parent->getContext()->getInput()->postGet('__platform') : $parent->getContext()->getInput()->postGet('platform')
        ]);

        $list = $result = [];
        foreach ($requests as $name => $params) {
            if (isset($parallelConf[$name])) {
                //服务名
                $list[$name]['service'] = $service = $parallelConf[$name]['service'] ?? null;
                $list[$name]['host'] = $parallelConf[$name]['host'] ?? $serviceConf[$service]['host'];
                $list[$name]['timeout'] = $parallelConf[$name]['timeout'] ?? $serviceConf[$service]['timeout'] ?? 1000;
                $list[$name]['api'] = $parallelConf[$name]['url'];
                //优先使用parallel配置，然后有参数有POST，无参用GET
                $list[$name]['method'] = $parallelConf[$name]['method'] ?? (empty($params) ? 'GET' : 'POST');
                $list[$name]['params'] = array_merge($params, $preParams); //合并参数;
                //结果解析方法
                $list[$name]['parser'] = ($parallelConf[$name]['parser'] ?? 'normal') . 'Parser';

                //dns请求
                $list[$name]['dns'] = $parent->getObject(Client::class)->goDnsLookup($list[$name]['host'], $list[$name]['timeout']);
            }
        }

        foreach ($list as $name => $item) {
            /**
             * @var Client $client
             */
            //dns结果
            if ($item['dns'] instanceof DNS) {
                $item['dns'] = yield $item['dns'];
                if (!$item['dns']) {
                    $parent->getContext()->getLog()->error('DNS lookup for ' . $item['host'] . ' Failed');
                    $result[$name] = false;
                    unset($list[$name]);
                    continue;
                }
            }

            //http请求
            if ($item['method'] == 'POST') {
                $list[$name]['http'] = $item['dns']->goPost($item['api'], $item['params'], $item['timeout']);
            } else {
                $list[$name]['http'] = $item['dns']->goGet($item['api'], $item['params'], $item['timeout']);
            }
        }

        foreach ($list as $name => $item) {
            $response = yield $item['http'];

            $request = ['host' => $item['host'], 'api' => $item['api'], 'method' => $item['method'], 'params' => $item['params']];

            // http 失败
            if (!isset($response['body']) || empty($response['body'])) {
                $parent->getContext()->getLog()->error('The response of body is not found with response: ' . json_encode($response) . ' Request: ' . json_encode($request));
                $result[$name] = false;
                unset($list[$name]);
                continue;
            }

            //解析结果
            $parser = $item['parser'];
            $result[$name] = $parser === 'noneParser' ? $response : self::$parser($request, $response, $parent);
        }

        return $result;
    }

    /**
     * 结果解析器(常规)
     *
     * @param array $request 请求信息
     * @param array $response 响应数据
     * @param Core $parent Core实例（通常为Controller实例）
     * @return mixed|null
     */
    protected static function normalParser(array $request, array $response, Core $parent)
    {
        try {
            $body = json_decode($response['body'], true);
            if ($body === null) {
                $error = static::jsonLastErrorMsg();
                throw new BusinessException('json decode failure: ' . $error . ' caused by ' . $response['body'] . ' Request: ' . json_encode($request));
            }

            return static::parseResponse($request, $body);
        } catch (BusinessException $businessException) {
            $parent->getContext()->getLog()->error($businessException->getMessage());
            return null;
        }
    }

    /**
     * 解析返回值
     *
     * @param array $request 请求参数
     * @param array $responseBody 响应正文
     * @return mixed
     * @throws BusinessException
     */
    private static function parseResponse(array $request, $responseBody)
    {
        if (isset($responseBody['status']) == false
            || array_key_exists('data', $responseBody) == false
            || isset($responseBody['message']) == false
        ) {
            throw new BusinessException('Response The result array is incomplete. response=' . json_encode($responseBody) . ' Request: ' . json_encode($request));
        }
        if ($responseBody['status'] != 200) {
            throw new BusinessException('Response returns the result status is not equal to 200. response=' . json_encode($responseBody) . ' Request: ' . json_encode($request));
        }

        return $responseBody['data'];
    }

    /**
     * 拿到json解析最后出现的错误信息
     *
     * @return mixed|string
     */
    private static function jsonLastErrorMsg()
    {
        $error = json_last_error();
        return array_key_exists($error, static::$jsonErrors) ? static::$jsonErrors[$error] : "Unknown error ({$error})";
    }
}
