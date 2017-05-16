<?php
/**
 * SerializePack
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pack;

class SerializePack implements IPack
{
    public function pack($data)
    {
        return serialize($data);
    }

    public function unPack($data)
    {
        return unserialize($data);
    }
}
