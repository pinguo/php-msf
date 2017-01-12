<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:20
 */

namespace Server\DataBase;


interface ICoroutineBase
{
    function send($callback);

    function getResult();
}