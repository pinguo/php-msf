<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-12-30
 * Time: 下午3:33
 */

namespace Server\Test;


use Server\CoreBase\CoroutineNull;

class TestResponse
{
    public $header = [];
    public $cookie = [];
    public $status = 0;
    public $gzip = 1;
    public $data = '';
    public $filename = '';
    private $result;
    private $lock = false;

    public function __construct()
    {
        $this->result = CoroutineNull::getInstance();
    }

    public function header(string $key, string $value)
    {
        $this->header[$key] = $value;
    }

    public function cookie(string $key, string $value = '', int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false)
    {
        $this->cookie[$key] = $value;
    }

    public function status(int $http_status_code)
    {
        $this->status = $http_status_code;
    }

    public function gzip(int $level = 1)
    {
        $this->gzip = $level;
    }

    public function write(string $data)
    {
        $this->data .= $data;
        $this->lock = true;
    }

    public function sendfile(string $filename)
    {
        $this->filename = $filename;
    }

    public function end(string $html)
    {
        if (!$this->lock) {
            $this->data = $html;
        }
        $this->result = $this;
    }

    public function getResult()
    {
        return $this->result;
    }
}