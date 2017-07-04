<?php
/**
 * common函数
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

$____GLOBAL_DUMP = '';

/**
 * 获取实例
 * @return \PG\MSF\MSFServer|\PG\MSF\MSFCli
 */
function &getInstance()
{
    return \PG\MSF\Server::getInstance();
}

/**
 * 获取服务器运行到现在的毫秒数
 * @return int
 */
function getTickTime()
{
    return \PG\MSF\MSFServer::getInstance()->tickTime;
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
 * 清理所有的定时器（请谨慎使用）
 */
function clearTimes()
{
    $timers = getInstance()->sysTimers;
    if (!empty($timers)) {
        foreach ($timers as $timerId) {
            swoole_timer_clear($timerId);
        }
    }
    swoole_event_exit();
}

/**
 * 内部打印变量
 *
 * @param $output
 * @param $var
 * @param $level
 */
function dumpInternal(&$output, $var, $level, $format = true, $truncated = true)
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
            if ($truncated && defined('DUMP_TRUNCATED') && strlen($var) > 512) {
                $output .= "'*<truncated>*'";
            } else {
                $output .= "'" . addslashes($var) . "'";
            }
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
                if ($format) {
                    $spaces = str_repeat(' ', $level * 4);
                } else {
                    $spaces = '';
                }

                $output .= '[';
                foreach ($keys as $key) {
                    if ($format) {
                        $output .= "\n" . $spaces . '    ';
                    }
                    dumpInternal($output, $key, 0, $format);
                    $output .= ' => ';
                    dumpInternal($output, $var[$key], $level + 1, $format);
                    if (!$format) {
                        $output .= ', ';
                    }
                }

                if ($format) {
                    $output .= "\n" . $spaces . ']';
                } else {
                    $output .= "], ";
                }
            }
            break;
        case 'object':
            if ($var instanceof \Throwable) {
                $truncated  = false;
                $dumpValues = [
                    'message' => $var->getMessage(),
                    'code'    => $var->getCode(),
                    'line'    => $var->getLine(),
                    'file'    => $var->getFile(),
                    'trace'   => $var->getTraceAsString(),
                ];
            } else {
                $dumpValues = (array)$var;
                $truncated  = true;
            }
            if (method_exists($var, '__sleep')) {
                $sleepProperties = $var->__sleep();
            } else {
                $sleepProperties = array_keys($dumpValues);
            }

            if (method_exists($var, '__unsleep')) {
                $unsleepProperties = $var->__unsleep();
            } else {
                $unsleepProperties = [];
            }
            $sleepProperties = array_diff($sleepProperties, $unsleepProperties);

            if (4 <= $level) {
                $output .= get_class($var) . '(...)';
            } else {
                $spaces = str_repeat(' ', $level * 4);
                $className = get_class($var);
                if ($format) {
                    $output .= "$className\n" . $spaces . '(';
                } else {
                    $output .= "$className(";
                }

                $i = 0;
                foreach ($dumpValues as $key => $value) {
                    if (!in_array($key, $sleepProperties)) {
                        break;
                    }

                    if ($i >= 100) {
                        if ($format) {
                            $output .= "\n" . $spaces . "    [...] => ...";
                        } else {
                            $output .= "... => ...";
                        }
                        break;
                    }
                    $i++;
                    $key = str_replace('*', '', $key);
                    $key = strtr(trim($key), "\0", ':');
                    $keyDisplay = strtr(trim($key), "\0", ':');
                    if ($format) {
                        $output .= "\n" . $spaces . "    [$keyDisplay] => ";
                    } else {
                        $output .= "$keyDisplay => ";
                    }
                    dumpInternal($output, $value, $level + 1, $format, $truncated);
                    if (!$format) {
                        $output .= ', ';
                    }
                }

                if ($format) {
                    $output .= "\n" . $spaces . ')';
                } else {
                    $output .= '), ';
                }
            }
            break;
    }

    if (!$format) {
        $output = str_replace([', ,', ',  ', ', )', ', ]'], [', ', ', ', ')', ']'], $output);
    }
}

/**
 * 打印变量
 *
 * @param $var
 * @param bool $format
 * @param bool $return
 * @return mixed
 */
function dump($var, $format = true, $return = false)
{
    global $____GLOBAL_DUMP;
    dumpInternal($____GLOBAL_DUMP, $var, 0, $format);
    if (!$return) {
        echo $____GLOBAL_DUMP, "\n";
        $____GLOBAL_DUMP = '';
    } else {
        $dump            = $____GLOBAL_DUMP;
        $____GLOBAL_DUMP = '';
        return $dump;
    }
}
