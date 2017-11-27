<?php
/**
 * Web Controller控制器基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\AOP\Wrapper;
use PG\AOP\MI;
use PG\MSF\Base\Core;
use Exception;

/**
 * Class Controller
 * @package PG\MSF\Controllers
 */
class Controller extends Core
{
    /**
     * @var Wrapper|\PG\MSF\Base\Pool 对象池
     */
    protected $objectPool;

    /**
     * @var array 当前请求已使用的对象列表
     */
    public $objectPoolBuckets = [];

    /**
     * @var float 请求开始处理的时间
     */
    public $requestStartTime = 0.0;

    /**
     * @var string TCP_REQUEST|HTTP_REQUEST 请求类型
     */
    public $requestType;

    /**
     * Controller constructor.
     *
     * @param string $controllerName controller标识
     * @param string $methodName method名称
     */
    public function __construct($controllerName, $methodName)
    {
        // 支持自动销毁成员变量
        MI::__supportAutoDestroy(static::class);
        $this->requestStartTime = microtime(true);
    }

    /**
     * 获取对象池
     *
     * @return Wrapper|\PG\MSF\Base\Pool
     */
    public function getObjectPool()
    {
        return $this->objectPool;
    }

    /**
     * 设置对象池
     *
     * @param Wrapper|\PG\MSF\Base\Pool|NULL $objectPool Pool实例
     * @return $this
     */
    public function setObjectPool($objectPool)
    {
        $this->objectPool = $objectPool;
        return $this;
    }

    /**
     * 设置请求类型
     *
     * @param string $requestType TCP_REQUEST|HTTP_REQUEST
     * @return $this
     */
    public function setRequestType($requestType)
    {
        $this->requestType = $requestType;
        return $this;
    }

    /**
     * 返回请求类型
     *
     * @return string
     */
    public function getRequestType()
    {
        return $this->requestType;
    }

    /**
     * 异常的回调
     *
     * @param \Throwable $e 异常实例
     * @throws \Throwable
     */
    public function onExceptionHandle(\Throwable $e)
    {
        try {
            if ($e->getPrevious()) {
                $ce     = $e->getPrevious();
                $errMsg = dump($ce, false, true);
            } else {
                $errMsg = dump($e, false, true);
                $ce     = $e;
            }
            $this->getContext()->getLog()->error($errMsg);
            if ($this->getContext()->getOutput()) {
                $this->output('Internal Server Error', 500);
            }
        } catch (\Throwable $ne) {
            getInstance()->log->error('previous exception ' . dump($ce, false, true));
            getInstance()->log->error('handle exception ' . dump($ne, false, true));
        }
    }

    /**
     * 请求处理完成销毁相关资源
     */
    public function destroy()
    {
        if ($this->getContext()) {
            $this->getContext()->getLog()->appendNoticeLog();
            //销毁对象池
            foreach ($this->objectPoolBuckets as $k => $obj) {
                $this->objectPool->push($obj);
                $this->objectPoolBuckets[$k] = null;
                unset($this->objectPoolBuckets[$k]);
            }
            $this->resetProperties();
            $this->__isConstruct = false;
            getInstance()->objectPool->push($this);
            parent::destroy();
        }
    }

    /**
     * 响应原始数据
     *
     * @param mixed|null $data 响应数据
     * @param int $status 响应HTTP状态码
     * @return void
     */
    public function output($data = null, $status = 200)
    {
        $this->getContext()->getOutput()->output($data, $status);
    }

    /**
     * 响应json格式数据
     *
     * @param mixed|null $data 响应数据
     * @param int $status 响应HTTP状态码
     * @return void
     */
    public function outputJson($data = null, $status = 200)
    {
        $this->getContext()->getOutput()->outputJson($data, $status);
    }

    /**
     * 通过模板引擎响应输出HTML
     *
     * @param array $data 待渲染KV数据
     * @param string|null $view 文件名
     * @throws \Exception
     * @throws \Throwable
     * @throws Exception
     * @return void
     */
    public function outputView(array $data, $view = null)
    {
        $this->getContext()->getOutput()->outputView($data, $view);
    }
}
