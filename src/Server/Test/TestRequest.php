<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-12-30
 * Time: 下午3:33
 */

namespace Server\Test;


class TestRequest
{
    public $header = [];
    public $server = [];
    public $get = [];
    public $post = [];
    public $cookie = [];
    public $files = [];
    public $_rawContent = '';

    public function __construct($path_info, $header = [], $get = [], $post = [], $cookie = [])
    {
        $this->setControllerName($path_info);
        $this->header = $header;
        $this->get = $get;
        $this->post = $post;
        $this->cookie = $cookie;
    }

    /**
     * eq:/TestController/test
     * @param $path_info
     */
    public function setControllerName($path_info)
    {
        $this->server['path_info'] = $path_info;
    }

    public function rawContent()
    {
        return $this->_rawContent;
    }
}