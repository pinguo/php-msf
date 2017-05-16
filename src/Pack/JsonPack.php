<?php
/**
 * JsonPack
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pack;

use PG\MSF\Base\Exception;

class JsonPack implements IPack
{
    public function pack($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function unPack($data)
    {
        $value = json_decode($data);
        if (empty($value)) {
            throw new Exception('json unPack 失败');
        }
        return $value;
    }
}
