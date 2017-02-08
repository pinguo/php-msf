<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午1:44
 */

namespace app\Models;


use Server\CoreBase\Model;

class AppModel extends Model
{
    public function test()
    {
        $result = [];
        $result[] = strlen(file_get_contents('http://rec-dev.camera360.com/'));
        $result[] = strlen(file_get_contents('http://rec-dev.camera360.com/'));
        $result[] = strlen(file_get_contents('http://rec-dev.camera360.com/'));

        return $result;
    }

    public function testCoroutine()
    {
        $result = [];
        $httpClient1 = yield $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient2 = yield $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient3 = yield $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $result[] = strlen(yield $httpClient1->coroutineGet('/'));
        $result[] = strlen(yield $httpClient2->coroutineGet('/'));
        $result[] = strlen(yield $httpClient3->coroutineGet('/'));

        return $result;
    }
}
