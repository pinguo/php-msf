<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\DataBase;


use Server\CoreBase\CoroutineBase;
use Server\CoreBase\SwooleException;

class MySqlCoroutine extends CoroutineBase
{
    /**
     * @var MysqlAsynPool
     */
    public $mysqlAsynPool;
    public $bind_id;
    public $sql;

    public function __construct($_mysqlAsynPool, $_bind_id = null, $_sql = null)
    {
        parent::__construct();
        $this->mysqlAsynPool = $_mysqlAsynPool;
        $this->bind_id = $_bind_id;
        $this->sql = $_sql;
        $this->request = '#Mysql:' . $_sql;
        $this->send(function ($result) {
            $this->result = $result;
        });
    }

    public function send($callback)
    {
        $this->mysqlAsynPool->query($callback, $this->bind_id, $this->sql);
    }

    public function getResult()
    {
        $result = parent::getResult();
        if(is_array($result)&&isset($result['error'])){
            throw new SwooleException($result['error']);
        }
        return $result;
    }
}