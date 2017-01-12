<?php
namespace app\Controllers;

use app\Protobuf\Login_Request;
use app\Protobuf\Login_Response;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午3:51
 */
class Account extends ProtoController
{
    public function login(Login_Request $loginRequest)
    {
        $loginResponse = new Login_Response();
        $loginResponse->setUsername('test');
        $loginResponse->setPassword('123');
        $this->makeMessageData($loginResponse);
        $this->send($this->Message);
    }
}
