<?php
/**
 * IRoute接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Route;

interface IRoute
{
    function handleClientData($data);

    function handleClientRequest($request);

    function getControllerName();

    function getMethodName();

    function getParams();

    function getPath();

    function setControllerName($name);

    function setMethodName($name);
}