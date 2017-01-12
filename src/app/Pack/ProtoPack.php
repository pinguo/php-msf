<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;

use app\Protobuf\Message;
use Server\CoreBase\SwooleException;

class ProtoPack implements IPack
{
    /**
     * @param $data Message
     * @return string
     */
    public function pack($data)
    {
        return $data->toStream()->getContents();
    }

    /**
     * @param $data string
     * @return mixed
     */
    public function unPack($data)
    {
        if (empty($data)) {
            throw new SwooleException('unpack error');
        }
        $message = new Message($data);
        $cmd_service = $message->getCmdService();
        $cmd_method = $message->getCmdMethod();
        $clientData = new \stdClass();
        $clientData->controller_name = $cmd_service->name();
        $clientData->method_name = $cmd_method->name();
        $clientData->data = $message;
        $request = $message->getRequest()??null;
        if (empty($request)) {
            throw new SwooleException('unpack error');
        }
        $method = "getM{$clientData->method_name}Request";
        if (!method_exists($request, $method)) {
            throw new SwooleException('unpack method error');
        }
        $clientData->params = call_user_func([$request, $method]);
        return $clientData;
    }
}
