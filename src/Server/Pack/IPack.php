<?php
/**
 * IPack接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Pack;

interface IPack
{
    function pack($data);

    function unPack($data);
}