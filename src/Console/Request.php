<?php
/**
 * MSF Console Request
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Console;

class Request
{
    public $server;
    public $get;
    public $post;
    public $header;

    public function getServer()
    {
        if ($this->server === null) {
            if (isset($_SERVER['argv'])) {
                $this->server = $_SERVER['argv'];
                array_shift($this->server);
            } else {
                $this->server = [];
            }
        }

        return $this->server;
    }

    public function setServer($params)
    {
        $this->server = $params;
    }

    public function resolve()
    {
        $rawParams = $this->getServer();
        if (isset($rawParams[0])) {
            $route = $rawParams[0];
            array_shift($rawParams);
        } else {
            $route = '';
        }

        $params = [];
        foreach ($rawParams as $param) {
            if (preg_match('/^--(\w+)(?:=(.*))?$/', $param, $matches) || preg_match('/^-(\w+)(?:=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                $params[$name] = isset($matches[2]) ? $matches[2] : true;
            } else {
                $params[] = $param;
            }
        }

        $this->server['path_info'] = $route;
        $this->get                 = $params;
        $this->post                = $params;

        return [$route, $params];
    }
}
