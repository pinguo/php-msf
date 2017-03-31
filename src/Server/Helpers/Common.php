<?php
/**
 * common函数
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

/**
 * 获取实例
 * @return \PG\MSF\Server\SwooleDistributedServer
 */
function &getInstance()
{
    return \PG\MSF\Server\SwooleDistributedServer::getInstance();
}

/**
 * 获取服务器运行到现在的毫秒数
 * @return int
 */
function getTickTime()
{
    return \PG\MSF\Server\SwooleDistributedServer::getInstance()->tickTime;
}

function getMillisecond()
{
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
}

function shellRead()
{
    $fp = fopen('/dev/stdin', 'r');
    $input = fgets($fp, 255);
    fclose($fp);
    $input = chop($input);
    return $input;
}

/**
 * http发送文件
 * @param $path
 * @param $request
 * @param $response
 * @return bool
 */
function httpEndFile($path, $request, $response)
{
    $path = urldecode($path);
    if (!file_exists($path)) {
        return false;
    }
    $lastModified = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';
    //缓存
    if (isset($request->header['if-modified-since']) && $request->header['if-modified-since'] == $lastModified) {
        $response->status(304);
        $response->end();
        return true;
    }
    $extension = getExtension($path);
    $normalHeaders = getInstance()->config->get("fileHeader.normal", ['Content-Type: application/octet-stream']);
    $headers = getInstance()->config->get("fileHeader.$extension", $normalHeaders);
    foreach ($headers as $value) {
        list($hk, $hv) = explode(': ', $value);
        $response->header($hk, $hv);
    }
    $response->header('Last-Modified', $lastModified);
    $response->sendfile($path);
    return true;
}

/**
 * 获取后缀名
 * @param $file
 * @return mixed
 */
function getExtension($file)
{
    $info = pathinfo($file);
    return strtolower($info['extension']??'');
}

/**
 * 获取绝对地址
 * @param $path
 * @return string
 */
function getWww($path)
{
    $normal = 'http://localhost:' . getInstance()->config['http_server']['port'];
    return getInstance()->config->get('http.domain', $normal) . '/' . $path;
}

function isMac()
{
    $str = PHP_OS;
    if ($str == 'Darwin') {
        return true;
    } else {
        return false;
    }
}

/**
 * 剔出协程相关上下文信息
 * @param $output
 * @param $var
 * @param $level
 */
function dumpCoroutineTaskMessage(&$output, $var, $level)
{
    switch (gettype($var)) {
        case 'boolean':
            $output .= $var ? 'true' : 'false';
            break;
        case 'integer':
            $output .= "$var";
            break;
        case 'double':
            $output .= "$var";
            break;
        case 'string':
            $output .= "'" . addslashes($var) . "'";
            break;
        case 'resource':
            $output .= '{resource}';
            break;
        case 'NULL':
            $output .= 'null';
            break;
        case 'unknown type':
            $output .= '{unknown}';
            break;
        case 'array':
            if (4 <= $level) {
                $output .= '[...]';
            } elseif (empty($var)) {
                $output .= '[]';
            } else {
                $keys = array_keys($var);
                $output .= '[';
                foreach ($keys as $key) {
                    dumpCoroutineTaskMessage($output, $key, 0);
                    $output .= ' => ';
                    dumpCoroutineTaskMessage($output, $var[$key], $level + 1);
                    $output .= ', ';
                }
                $output .= "], ";
            }
            break;
        case 'object':
            if ($var instanceof \PG\MSF\Server\Helpers\Context || $var instanceof \PG\MSF\Server\CoreBase\Controller) {
                $output .= '..., ';
                break;
            }
            if (4 <= $level) {
                $output .= get_class($var) . '(...)';
            } else {
                $className = get_class($var);
                $output .= "$className(";
                if ('__PHP_Incomplete_Class' !== get_class($var) && method_exists($var, '__debugInfo')) {
                    $dumpValues = $var->__debugInfo();
                } else {
                    $dumpValues = (array) $var;
                }
                foreach ($dumpValues as $key => $value) {
                    $keyDisplay = strtr(trim($key), "\0", ':');
                    $output .= "$keyDisplay => ";
                    dumpCoroutineTaskMessage($output, $value, $level + 1);
                    $output .= ', ';
                }
                $output .= '), ';
            }
            break;
    }

    $output = str_replace([', ,', ',  ', ', )', ', ]'], [', ', ', ', ')', ']'], $output);
}