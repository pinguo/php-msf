<?php
/**
 * @desc: 并行httpClient
 * @author: leandre <niulingyun@camera360.com>
 * @date: 14/06/2017
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Client;

use PG\Exception\BusinessException;
use PG\MSF\Base\Core;
use PG\MSF\Client\Http\Client;

class ConcurrentClient
{
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
     * @param array $requests
     * @param Core $parent
     * @return array
     */
    public static function requestByConcurrent(array $requests, Core $parent)
    {
        $config = $parent->getConfig();
        $serviceConf = $config->get('params.service', null);
        $parallelConf = $config->get('params.parallel');

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
                $list[$name]['params'] = $list[$name]['method'] == 'POST' ? $params : http_build_query($params);
                //结果解析方法
                $list[$name]['parser'] = ($parallelConf[$name]['parser'] ?? 'normal') . 'Parser';

                //dns请求
                $list[$name]['dns'] = $parent->getContext()->getObjectPool()->get(Client::class)->coroutineGetHttpClient($list[$name]['host'], $list[$name]['timeout']);
            }
        }

        foreach ($list as $name => $item) {
            //dns结果
            $dnsClient = yield $item['dns'];

            //dns失败
            if ($dnsClient == null) {
                $parent->getContext()->getLog()->error('DNS lookup for ' . $item['host'] . ' Failed');
                $result[$name] = false;
                unset($list[$name]);
                continue;
            }

            //http请求
            if ($item['method'] == 'POST') {
                $list[$name]['http'] = $dnsClient->coroutinePost($item['api'], $item['params'], $item['timeout']);
            } else {
                $list[$name]['http'] = $dnsClient->coroutineGet($item['api'], $item['params'], $item['timeout']);
            }
        }

        foreach ($list as $name => $item) {
            $response = yield $item['http'];

            // http 失败
            if (!isset($response['body'])) {
                $parent->getContext()->getLog()->error('Request for ' . $item['host'] . $item['api'] . ' Failed, Method: ' . $item['method'] . ' Params: ' . json_encode($item['params']) . ' Response: ' . json_encode($response));
                $result[$name] = false;
                unset($list[$name]);
                continue;
            }

            //解析结果
            $parser = $item['parser'];
            $result[$name] = $parser === 'noneParser' ? $response : self::$parser($response, $parent);
        }

        return $result;
    }

    /**
     * 结果解析器(常规)
     * @param array $response
     * @param Core $parent
     * @return mixed|null
     */
    protected static function normalParser(array $response, Core $parent)
    {
        try {
            $body = json_decode($response['body'], true);
            if ($body === null) {
                $error = static::jsonLastErrorMsg();
                throw new BusinessException('json decode failure: ' . $error . ' caused by ' . $response['body']);
            }

            return static::parseResponse($body);
        } catch (BusinessException $businessException) {
            $parent->getContext()->getLog()->error($businessException->getMessage());
            return null;
        }
    }

    /**
     * 解析返回值
     *
     * @param $responseBody
     * @return mixed
     * @throws BusinessException
     */
    private static function parseResponse($responseBody)
    {
        if (isset($responseBody['status']) == false
            || array_key_exists('data', $responseBody) == false
            || isset($responseBody['message']) == false
        ) {
            throw new BusinessException('Response The result array is incomplete. response=' . json_encode($responseBody));
        }
        if ($responseBody['status'] != 200) {
            throw new BusinessException('Response returns the result status is not equal to 200. response=' . json_encode($responseBody));
        }

        return $responseBody['data'];
    }

    /**
     * 拿到 json 解析最后出现的错误信息
     * @return mixed|string
     */
    private static function jsonLastErrorMsg()
    {
        $error = json_last_error();
        return array_key_exists($error, static::$jsonErrors) ? static::$jsonErrors[$error] : "Unknown error ({$error})";
    }
}
