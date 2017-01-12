<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;


class MsgPack implements IPack
{
    public function pack($data)
    {
        return msgpack_pack($data);
    }

    public function unPack($data)
    {
        return msgpack_unpack($data);
    }
}