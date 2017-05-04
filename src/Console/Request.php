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
    public $_params;

    public function getParams()
    {
        if ($this->_params === null) {
            if (isset($_SERVER['argv'])) {
                $this->_params = $_SERVER['argv'];
                array_shift($this->_params);
            } else {
                $this->_params = [];
            }
        }

        return $this->_params;
    }

    public function setParams($params)
    {
        $this->_params = $params;
    }

    public function resolve()
    {
        $rawParams = $this->getParams();
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

        return [$route, $params];
    }
    
    
}
