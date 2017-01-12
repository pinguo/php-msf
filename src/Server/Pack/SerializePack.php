<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;


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