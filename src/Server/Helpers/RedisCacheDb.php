<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-7
 * Time: 上午10:08
 */
/**
 * 将一个二维数组以field字段为索引序列化到redis的hash表中
 * @param string $key hash表的key
 * @param array $array 二维数组
 * @param string $field 索引字段不传默认0-N,一般为数据库字段名
 */
function redis_putToRedisHash($key, $array, $field_name_db = '')
{
    $redis = MyRedis::getInstance();
    $set_array = array();
    if (empty($field_name_db)) {
        foreach ($array as $value) {
            array_push($set_array, json_encode($value));
        }
    } else {
        foreach ($array as $value) {
            $set_array[$value[$field_name_db]] = json_encode($value);
        }
    }
    $redis->hMset($key, $set_array);
    return $set_array;
}

/**
 * 从redis中批量获取,方法不保证全部都能有值
 * @param string $key
 * @param array $field 为空代表取全部 例子array(1,2,3)
 * @return array 额定为二维数组,不存在返回null
 */
function redis_getHashFromRedis($key, $fields = null)
{
    $redis = MyRedis::getInstance();
    if (empty($fields)) {
        $result = $redis->hGetAll($key);
    } else {
        $result = $redis->hmGet($key, $fields);
    }
    if ($result) {//存在
        foreach ($result as $key => $value) {
            $result[$key] = json_decode($value, true);
        }
        return $result;
    } else {
        return null;
    }
}

/**
 * 先从redis中找，找不到再从db中找，存入redis
 * @param $db 数据库查询方法，不要存在where in语句如果在redis中没有命中where方法将被修改
 * @param string $hashkey_dbtable hash键名，数据库表名
 * @param string $hashfield_dbfield field的唯一名称，数据存入hash的唯一索引，field必须是数据库存在的字段名
 * @param array $field_values $hashfield_dbfield在数据库对应的值的数组
 * @param string $dbfield_other_name 别名
 * @return array 找不到为null，否则额定为二维数组@data_from后面表示数据来源
 */
function redis_getHashFromRedisAndDb($hashkey_dbtable, $hashfield_dbfield, $field_values, $db, $dbfield_other_name = '')
{
    $result = redis_getHashFromRedis($hashkey_dbtable, $field_values);
    $needSeachFromDb = array();
    foreach ($field_values as $value) {
        if (empty($result[$value])) {
            array_push($needSeachFromDb, $value);
        } else {
            $result[$value]['@data_from'] = 'redis';
        }
    }
    if (!empty($needSeachFromDb)) {//此处处理未命中数据，注意where方法将被修改
        $resultFromDb = $db->where_in($hashfield_dbfield,$needSeachFromDb)->get()->result_array();//向db请求
        if (empty($resultFromDb)) {//代表从数据库中找不到
            return $result;
        }
        if (empty($dbfield_other_name)) {
            $dbfield_other_name = $hashfield_dbfield;
        }
        redis_putToRedisHash($hashkey_dbtable, $resultFromDb, $dbfield_other_name); //写入redis
        foreach ($resultFromDb as $value) {
            $result[$value[$dbfield_other_name]] = $value; //拼合数据
            $result[$value[$dbfield_other_name]]['@data_from'] = 'db';
        }
    }
    $db->reset_query();
    return $result;
}

/**
 * 批量更新Redis中的数据（不支持插入数据），先从redis批量获取数据->合并数据->传回redis，该方法适合不完整的数据合并，
 * 插入数据请直接使用putToRedisHash，切记
 * 如果redis中不含有对应的数据，强制合并后的数据也将是不完整的，
 * 所以redis中不存在的数据，使用该方法将会忽略掉。
 * @param string $redis_name
 * @param string $hashkey
 * @param array $updateValueArrary 二维数组符合redisfordb返回的结构{$field=>{},$field=>{}};
 */
function redis_updateHashToRedis($hashkey, $updateValueArrary)
{
    $result = array();
    $fields = array_keys($updateValueArrary);
    $resultFromRedis = redis_getHashFromRedis($hashkey, $fields);
    foreach ($resultFromRedis as $r_key => $r_value) {//剔除不完整的数据
        if (!empty($r_value)) {
            $result[$r_key] = json_encode(array_merge($resultFromRedis[$r_key], $updateValueArrary[$r_key]));
        }
    }
    $redis = MyRedis::getInstance();
    $redis->hMset($hashkey, $result);
}

/**
 * 删除名为key的hash中的域
 * @param $key
 * @param $field
 */
function redis_delHashField($key, $field)
{
    $redis = MyRedis::getInstance();
    $redis->hDel($key, $field);
}