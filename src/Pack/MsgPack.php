<?php
/**
 * MsgPack
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pack;

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
