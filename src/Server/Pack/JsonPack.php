<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;

use Server\CoreBase\SwooleException;

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
            throw new SwooleException('json unPack 失败');
        }
        return $value;
    }
}