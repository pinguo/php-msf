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
use PG\Log\PGLog;
use PG\MSF\{
    Base\Core, Base\Input, Base\Output, Base\AOPFactory, Base\Exception,
    DataBase\MysqlAsynPool, Marco, Server, Coroutine\CException, Helpers\Context
};

class Controller extends Core
{
    /**
     * 是否来自http的请求不是就是来自tcp
     * @var string
     */
    public $requestType;
    /**
     * @var Wrapper|\PG\MSF\Memory\Pool
     */
    public $objectPool;
    /**
     * @var array
     */
    public $objectPoolBuckets = [];
    /**
     * fd
     * @var int
     */
    protected $fd;
    /**
     * uid
     * @var int
     */
    protected $uid;
    /**
     * 用户数据
     * @var
     */
    protected $clientData;
    /**
     * http response
     * @var \swoole_http_request
     */
    protected $request;
    /**
     * http response
     * @var \swoole_http_response
     */
    protected $response;
    /**
     * 用于单元测试模拟捕获服务器发出的消息
     * @var array
     */
    protected $testUnitSendStack = [];
    /**
     * 控制器名称
     * @var string
     */
    protected $controllerName = '';
    /**
     * 方法名称
     * @var string
     */
    protected $methodName = '';

    /**
     * redis连接池
     * @var array
     */
    private $redisPools;
    /**
     * redis代理池
     * @var array
     */
    private $redisProxies;

    /**
     * @var float 请求开始处理的时间
     */
    public $requestStartTime = 0.0;

    /**
     * Controller constructor.
     */
    final public function __construct()
    {
        parent::__construct();
        $this->objectPool = AOPFactory::getObjectPool(getInstance()->objectPool, $this);
    }

    /**
     * 设置客户端协议数据
     * @param $uid
     * @param $fd
     * @param $clientData
     * @param $controllerName
     * @param $methodName
     */
    public function setClientData($uid, $fd, $clientData, $controllerName, $methodName)
    {
        $this->uid = $uid;
        $this->fd  = $fd;
        $this->clientData = $clientData;
        $this->requestType = Marco::TCP_REQUEST;
        $this->initialization($controllerName, $methodName);
    }

    /**
     * 初始化每次执行方法之前都会执行initialization
     * @param string $controllerName 准备执行的controller名称
     * @param string $methodName 准备执行的method名称
     */
    public function initialization($controllerName, $methodName)
    {
        $this->controllerName = $controllerName;
        $this->methodName = $methodName;
        $this->requestStartTime = microtime(true);
    }

    /**
     * set http Request Response
     * @param $request
     * @param $response
     * @param $controllerName
     * @param $methodName
     */
    public function setRequestResponse($request, $response, $controllerName, $methodName)
    {
        $this->request     = $request;
        $this->response    = $response;
        $this->requestType = Marco::HTTP_REQUEST;
        $this->initialization($controllerName, $methodName);
    }

    /**
     * 异常的回调
     * @param \Throwable $e
     * @throws \Throwable
     */
    public function onExceptionHandle(\Throwable $e)
    {
        $errMsg = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $errMsg .= ' Trace: ' . $e->getTraceAsString();
        if (!empty($e->getPrevious())) {
            $errMsg .= ' Previous trace: ' . $e->getPrevious()->getTraceAsString();
        }
        if ($e instanceof ParameterValidationExpandException) {
            $this->getContext()->getLog()->warning($errMsg . ' with code ' . Errno::PARAMETER_VALIDATION_FAILED);
            $this->outputJson(parent::$stdClass, $e->getMessage(), Errno::PARAMETER_VALIDATION_FAILED);
        } elseif ($e instanceof PrivilegeException) {
            $this->getContext()->getLog()->warning($errMsg . ' with code ' . Errno::PRIVILEGE_NOT_PASS);
            $this->outputJson(parent::$stdClass, $e->getMessage(), Errno::PRIVILEGE_NOT_PASS);
        } elseif ($e instanceof \MongoException) {
            $this->getContext()->getLog()->error($errMsg . ' with code ' . $e->getCode());
            $this->outputJson(parent::$stdClass, 'Network Error.', Errno::FATAL);
        } elseif ($e instanceof CException) {
            $this->getContext()->getLog()->error($errMsg . ' with code ' . $e->getCode());
            $this->outputJson(parent::$stdClass, $e->getPreviousMessage(), $e->getCode());
        } else {
            $this->getContext()->getLog()->error($errMsg . ' with code ' . $e->getCode());
            $this->outputJson(parent::$stdClass, $e->getMessage(), $e->getCode());
        }
    }

    /**
     * 向当前客户端发送消息
     * @param $data
     * @param $destroy
     * @throws Exception
     */
    public function send($data, $destroy = true)
    {
        if ($this->isDestroy) {
            throw new Exception('controller is destroy can not send data');
        }
        $data = getInstance()->encode($this->pack->pack($data));
        if (Server::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'send', 'fd' => $this->fd, 'data' => $data];
        } else {
            getInstance()->send($this->fd, $data);
        }
        if ($destroy) {
            $this->destroy();
        }
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        $this->getContext()->getLog()->appendNoticeLog();
        parent::destroy();
        unset($this->fd);
        unset($this->uid);
        unset($this->controllerName);
        unset($this->methodName);
        unset($this->clientData);
        unset($this->request);
        unset($this->response);
        unset($this->redisProxies);
        unset($this->redisPools);
        //销毁对象池
        foreach ($this->objectPoolBuckets as $k => $obj) {
            $this->objectPool->push($obj);
            unset($this->objectPoolBuckets[$k]);
        }

        ControllerFactory::getInstance()->revertController($this);
    }

    /**
     * 获取单元测试捕获的数据
     * @return array
     */
    public function getTestUnitResult()
    {
        $stack = $this->testUnitSendStack;
        $this->testUnitSendStack = [];
        return $stack;
    }

    /**
     * 当控制器方法不存在的时候的默认方法
     */
    public function defaultMethod()
    {
        if ($this->requestType == Marco::HTTP_REQUEST) {
            $this->getContext()->getOutput()->setHeader('HTTP/1.1', '404 Not Found');
            $template = $this->loader->view('server::error_404');
            $this->getContext()->getOutput()->end($template->render());
        } else {
            throw new Exception('method not exist');
        }
    }

    /**
     * 断开链接
     * @param $fd
     * @param bool $autoDestroy
     */
    protected function close($fd, $autoDestroy = true)
    {
        if (Server::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'close', 'fd' => $fd];
        } else {
            getInstance()->close($fd);
        }
        if ($autoDestroy) {
            $this->destroy();
        }
    }

    /**
     * 获取redis连接池
     * @param string $poolName
     * @return bool|Wrapper|\PG\MSF\DataBase\CoroutineRedisHelp
     */
    protected function getRedisPool(string $poolName)
    {
        if (isset($this->redisPools[$poolName])) {
            return $this->redisPools[$poolName];
        }
        $pool = getInstance()->getAsynPool($poolName);
        if (!$pool) {
            return false;
        }

        $this->redisPools[$poolName] = AOPFactory::getRedisPoolCoroutine($pool->getCoroutine(), $this);
        return $this->redisPools[$poolName];
    }

    /**
     * 获取redis代理
     * @param string $proxyName
     * @return bool|Wrapper
     */
    protected function getRedisProxy(string $proxyName)
    {
        if (isset($this->redisProxies[$proxyName])) {
            return $this->redisProxies[$proxyName];
        }
        $proxy = getInstance()->getRedisProxy($proxyName);
        if (!$proxy) {
            return false;
        }

        $this->redisProxies[$proxyName] = AOPFactory::getRedisProxy($proxy, $this);
        return $this->redisProxies[$proxyName];
    }

    /**
     * 响应json格式数据
     *
     * @param null $data
     * @param string $message
     * @param int $status
     * @param null $callback
     * @return array
     */
    public function outputJson(
        $data = null,
        $message = '',
        $status = 200,
        $callback = null
    ) {
        $this->getContext()->getOutput()->outputJson($data, $message, $status, $callback);
    }
}
