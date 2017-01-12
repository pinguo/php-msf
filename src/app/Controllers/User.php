<?php
/**
 * Created by PhpStorm.
 * User: niulingyun
 * Date: 17-1-12
 * Time: 上午10:32
 */

namespace app\Controllers;

use Server\CoreBase\Controller;

class User extends Controller
{
    public function Info()
    {
        $clientData = $this->client_data->data;

        $userIds = $clientData->userIds;

        $userModel = $this->loader->model('UserModel', $this);
        $data = yield $userModel->getUsersInfo($userIds);

        $this->send($data);
    }

    public function http_Info()
    {
        $userIds = $this->http_input->get('userIds');
        $userIds = explode(',', $userIds);
        $userModel = $this->loader->model('UserModel', $this);
        $data = yield $userModel->getUsersInfo($userIds);
        $this->http_output->end(json_encode($data));
    }
}