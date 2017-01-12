<?php
/**
 * Created by PhpStorm.
 * User: niulingyun
 * Date: 17-1-12
 * Time: 上午10:35
 */

namespace app\Models;


use Server\CoreBase\Model;

class UserModel extends Model
{
    public function getUsersInfo(array $userIds)
    {
        $data = [];
        $cacheData = yield $this->redis_pool->coroutineSend('mget', $userIds);
        $noCacheIds = [];
        foreach ($userIds as $i => $userId) {
            if (isset($cacheData[$i]) && $cacheData[$i] != false) {
                $data[$userId] = json_decode($cacheData[$i], true);
            } else {
                $noCacheIds[] = $userId;
            }
        }

        if ($noCacheIds) {
            $httpClient = yield $this->client->coroutineGetHttpClient('http://i.camera360.com:80');
            $httpClient->setHeaders(['Host' => 'i.camera360.com']);
            $result = yield $httpClient->coroutineGet('/inner/user/multi', ['userIds' => implode(',', $noCacheIds)]);
            $result = json_decode($result, true);
            if ($result && $result['status'] == 200) {
                $retData = $result['data'];
                $toCache = [];
                foreach ($retData as $uid => $info) {
                    $data[$uid] = $info;
                    $toCache[$uid] = json_encode($info);
                }
                yield $this->redis_pool->coroutineSend('mset', $toCache);
            }
        }

        return $data;
    }
}
