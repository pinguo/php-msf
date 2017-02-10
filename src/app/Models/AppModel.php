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
        $result[] = strlen(file_get_contents('http://rec-dev.camera360.com/'));
        $result[] = strlen(file_get_contents('http://rec-dev.camera360.com/'));
        $result[] = strlen(file_get_contents('http://rec-dev.camera360.com/'));

        return $result;
    }

    public function testCurlMulti()
    {
        $ch1 = curl_init();
        $ch2 = curl_init();
        $ch3 = curl_init();
        $ch4 = curl_init();
        $ch5 = curl_init();
        $ch6 = curl_init();

        curl_setopt($ch1, CURLOPT_URL, "http://rec-dev.camera360.com/");
        curl_setopt($ch1, CURLOPT_HEADER, 0);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch2, CURLOPT_URL, "http://rec-dev.camera360.com/");
        curl_setopt($ch2, CURLOPT_HEADER, 0);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch3, CURLOPT_URL, "http://rec-dev.camera360.com/");
        curl_setopt($ch3, CURLOPT_HEADER, 0);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch4, CURLOPT_URL, "http://rec-dev.camera360.com/");
        curl_setopt($ch4, CURLOPT_HEADER, 0);
        curl_setopt($ch4, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch5, CURLOPT_URL, "http://rec-dev.camera360.com/");
        curl_setopt($ch5, CURLOPT_HEADER, 0);
        curl_setopt($ch5, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch6, CURLOPT_URL, "http://rec-dev.camera360.com/");
        curl_setopt($ch6, CURLOPT_HEADER, 0);
        curl_setopt($ch6, CURLOPT_RETURNTRANSFER, 1);

        $mh = curl_multi_init();

        curl_multi_add_handle($mh,$ch1);
        curl_multi_add_handle($mh,$ch2);
        curl_multi_add_handle($mh,$ch3);
        curl_multi_add_handle($mh,$ch4);
        curl_multi_add_handle($mh,$ch5);
        curl_multi_add_handle($mh,$ch6);
        $active = null;

        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        $result[] = strlen(curl_multi_getcontent($ch1));
        $result[] = strlen(curl_multi_getcontent($ch2));
        $result[] = strlen(curl_multi_getcontent($ch3));
        $result[] = strlen(curl_multi_getcontent($ch4));
        $result[] = strlen(curl_multi_getcontent($ch5));
        $result[] = strlen(curl_multi_getcontent($ch6));

        curl_multi_remove_handle($mh, $ch1);
        curl_multi_remove_handle($mh, $ch2);
        curl_multi_remove_handle($mh, $ch3);
        curl_multi_remove_handle($mh, $ch4);
        curl_multi_remove_handle($mh, $ch5);
        curl_multi_remove_handle($mh, $ch6);
        curl_multi_close($mh);
        curl_close($ch1);
        curl_close($ch2);
        curl_close($ch3);
        curl_close($ch4);
        curl_close($ch5);
        curl_close($ch6);

        return $result;
    }

    public function testCoroutine()
    {
        $result = [];
        $httpClient1 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient2 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient3 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient4 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient5 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient6 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');

        $c1 = yield $httpClient1;
        $c2 = yield $httpClient2;
        $c3 = yield $httpClient3;
        $c4 = yield $httpClient4;
        $c5 = yield $httpClient5;
        $c6 = yield $httpClient6;

        $r1 = $c1->coroutineGet('/');
        $r2 = $c2->coroutineGet('/');
        $r3 = $c3->coroutineGet('/');
        $r4 = $c4->coroutineGet('/');
        $r5 = $c5->coroutineGet('/');
        $r6 = $c6->coroutineGet('/');

        $result[] = strlen(yield $r1);
        $result[] = strlen(yield $r2);
        $result[] = strlen(yield $r3);
        $result[] = strlen(yield $r4);
        $result[] = strlen(yield $r5);
        $result[] = strlen(yield $r6);

        return $result;
    }

    public function nested()
    {
        $result = [];
        $httpClient1 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient2 = $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');

        $c1 = yield $httpClient1;
        $c2 = yield $httpClient2;

        $r1 = $c1->coroutineGet('/');
        $r2 = $c2->coroutineGet('/');

        $result[] = strlen(yield $r1);
        $result[] = strlen(yield $r2);

        return $result;
    }

    public function nested2()
    {
        $result = [];
        $httpClient1 = yield $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');
        $httpClient2 = yield $this->client->coroutineGetHttpClient('http://rec-dev.camera360.com:80');

        $result[] = strlen(yield $httpClient1->coroutineGet('/'));
        $result[] = strlen(yield $httpClient2->coroutineGet('/'));

        return $result;
    }

}
