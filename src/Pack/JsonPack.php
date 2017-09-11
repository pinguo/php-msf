<?php
/**
 * JsonPack
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pack;

/**
 * Class JsonPack
 * @package PG\MSF\Pack
 */
class JsonPack implements IPack
{
    /**
     * pack JSON
     *
     * This adapter uses the json_encode PHP's functions.
     * For further details, please refer to the manual.
     * Manual : http://php.net/manual/en/function.json-encode.php
     *
     * @param  mixed  $data
     * @param  int  $options
     * @param  int  $depth
     * @return mixed
     */
    public function pack($data, $options = JSON_UNESCAPED_UNICODE, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }

    /**
     * unpack JSON
     *
     * This adapter uses the json_decode PHP's functions.
     * For further details, please refer to the manual.
     * Manual : http://php.net/manual/en/function.json-decode.php
     *
     * @param  string  $data
     * @param  ...  $params
     * @throws Exception
     * @return mixed
     */
    public function unPack($data, ...$params)
    {
        $value = json_decode($data, ...$params);
        if ($value === null && json_last_error() !== 0) {
            throw new Exception('Json unPack faild. Error message : ' .  json_last_error_msg());
        }
        return $value;
    }
}
