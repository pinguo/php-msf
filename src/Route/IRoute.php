<?php
/**
 * IRoute接口
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Route;

interface IRoute
{
    function handleClientData($data);

    function handleClientRequest($request);

    function getControllerName();

    function getMethodName();

    function getParams();

    function getPath();

    function getIsRpc();

    function setControllerName($name);

    function setMethodName($name);

    function setParams($params);
}