<?php
/**
 * IPack接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Pack;

/**
 * Interface IPack
 * @package PG\MSF\Pack
 */
interface IPack
{
    /**
     * 打包
     *
     * @param $data
     * @return string
     */
    public function pack($data);

    /**
     * 解包
     *
     * @param $data
     * @return mixed
     */
    public function unPack($data);
}
