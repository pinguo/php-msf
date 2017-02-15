<?php
namespace app\Tasks;

use Server\Tasks\MongoDbTask;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午1:06
 */
class AppTask extends MongoDbTask
{
    /**
     * 当前要用的配置  配置名，db名，collection名
     * @var array
     */
    public $mongoConf = ['test', 'test', 'test'];

    public function testTask()
    {
        return "test task\n";
    }

    public function testMongo()
    {
        return $this->mongoCollection->findOne();
    }
}
