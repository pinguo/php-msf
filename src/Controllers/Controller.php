<?php
/**
 * Controller 控制器
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\Exception\Errno;
use PG\Exception\ParameterValidationExpandException;
use PG\Exception\PrivilegeException;
use PG\AOP\Wrapper;
use PG\MSF\Base\Core;
use PG\MSF\Base\Child;
use Exception;
use PG\MSF\Coroutine\CException;

class Controller extends Core
{
    /**
     * @var Wrapper|\PG\MSF\Memory\Pool
     */
    protected $objectPool;

    /**
     * @var array
     */
    public $objectPoolBuckets = [];

    /**
     * fd
     * @var int
     */
    public $fd;

    /**
     * @var float 请求开始处理的时间
     */
    public $requestStartTime = 0.0;

    /**
     * 请求类型
     * @var string TCP_REQUEST|HTTP_REQUEST
     */
    public $requestType;

    /**
     * Controller constructor.
     *
     * @param string $controllerName controller名称
     * @param string $methodName method名称
     */
    public function __construct($controllerName, $methodName)
    {
        $this->__supportAutoDestroy();
        $this->requestStartTime = microtime(true);
    }

    /**
     * @return Wrapper|\PG\MSF\Memory\Pool
     */
    public function __getObjectPool()
    {
        return $this->objectPool;
    }

    /**
     * @return Wrapper|\PG\MSF\Memory\Pool
     */
    public function getObjectPool()
    {
        return $this->getContext()->getObjectPool();
    }

    /**
     * @param Wrapper|\PG\MSF\Memory\Pool|NULL $objectPool
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
     * @param \Throwable $e
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

            if ($ce instanceof ParameterValidationExpandException) {
                $this->getContext()->getLog()->warning($errMsg . ' with code ' . Errno::PARAMETER_VALIDATION_FAILED);
                $this->outputJson(parent::$stdClass, $ce->getMessage(), Errno::PARAMETER_VALIDATION_FAILED);
            } elseif ($ce instanceof PrivilegeException) {
                $this->getContext()->getLog()->warning($errMsg . ' with code ' . Errno::PRIVILEGE_NOT_PASS);
                $this->outputJson(parent::$stdClass, $ce->getMessage(), Errno::PRIVILEGE_NOT_PASS);
            } elseif ($ce instanceof \MongoException) {
                $this->getContext()->getLog()->error($errMsg . ' with code ' . $ce->getCode());
                $this->outputJson(parent::$stdClass, 'Network Error.', Errno::FATAL);
            } elseif ($ce instanceof CException) {
                $this->getContext()->getLog()->error($errMsg . ' with code ' . $ce->getCode());
                $this->outputJson(parent::$stdClass, $ce->getMessage(), $ce->getCode());
            } else {
                $this->getContext()->getLog()->error($errMsg . ' with code ' . $ce->getCode());
                $this->outputJson(parent::$stdClass, $ce->getMessage(), $ce->getCode());
            }
        } catch (\Throwable $ne) {
            getInstance()->log->error('previous exception ' . dump($ce, false, true));
            getInstance()->log->error('handle exception ' . dump($ne, false, true));
        }
    }

    /**
     * 销毁
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
            $this->objectPool->setCurrentObjParent(null);
        }
        $this->resetProperties(Child::$reflections[static::class]);
        $this->__isContruct = false;
        getInstance()->objectPool->push($this);
        parent::destroy();
    }

    /**
     * 响应json格式数据
     *
     * @param null $data
     * @param string $message
     * @param int $status
     * @param null $callback
     * @return void
     */
    public function outputJson(
        $data = null,
        $message = '',
        $status = 200,
        $callback = null
    ) {
        $this->getContext()->getOutput()->outputJson($data, $message, $status, $callback);
    }

    /**
     * 响应通过模板输出的HTML
     *
     * @param array $data
     * @param string|null $view
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
