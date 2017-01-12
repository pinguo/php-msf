<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-12-30
 * Time: 下午1:00
 */

namespace Server\Test;

use Server\CoreBase\Coroutine;
use Server\CoreBase\CoroutineTask;
use Server\CoreBase\GeneratorContext;

/**
 * 单元测试组件
 * Class TestModule
 * @package Server\test
 */
class TestModule
{
    private $failCount = 0;
    private $totalCount = 0;
    private $ignoreCount = 0;
    private $successCount = 0;
    private $docParser;
    private $tests;
    private $asyn = false;
    private $dir = '';
    public function __construct($dir, Coroutine $coroutine = null)
    {
        $this->dir = $dir;
        if (empty($dir)) {
            $dir = __DIR__ . "/../../test";
        }
        if ($coroutine != null) {
            $this->asyn = true;
            print_r("->开始异步的单元测试\n");
        } else {
            $this->asyn = false;
            print_r("->开始同步的单元测试\n");
        }
        $this->tests = [];
        $this->docParser = new DocParser();
        if (is_dir($dir)) {
            //获取本文件目录的文件夹地址
            $filesnames = scandir($dir);
        } else {
            $filesnames[] = $dir;
        }
        //获取也就是扫描文件夹内的文件及文件夹名存入数组 $filesnames
        foreach ($filesnames as $value) {
            $value = str_replace('.php', '', $value);
            $class_name = "\\test\\" . $value;
            if (class_exists($class_name)) {
                $reflection = new \ReflectionClass ($class_name);
                $doc = $reflection->getDocComment();
                $info = $this->docParser->parse($doc);
                $this->tests[$class_name]['@info'] = $info;
                $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
                //遍历所有的方法
                foreach ($methods as $method) {
                    //获取方法的注释
                    $doc = $method->getDocComment();
                    //解析注释
                    $info = $this->docParser->parse($doc);
                    $method_name = $method->getName();
                    if (substr($method_name, 0, 4) === 'test') {
                        $this->tests[$class_name][$method_name] = $info;
                    }
                }
            }
        }
        $generatorContext = new GeneratorContext();
        $generatorContext->setController($this, 'SwooleDistributedServer', 'TestModule');
        if ($coroutine != null) {
            $coroutine->start($this->runTests(), $generatorContext);
        } else {
            $coroutineTask = new CoroutineTask($this->runTests(), $generatorContext);
            while (true) {
                $coroutineTask->run();
                if ($coroutineTask->isFinished()) {
                    break;
                }
            }
            get_instance()->server->shutdown();
        }
    }

    /**
     * 运行测试方法
     */
    private function runTests()
    {
        foreach ($this->tests as $className => $classData) {
            $classInstance = new $className;
            if (!($classInstance instanceof TestCase)) {
                break;
            }
            $count = count($classData) - 1;
            $this->totalCount += $count;
            $description = $classData['@info']['description']??'';
            if ($this->asyn) {
                print_r("├──\e[30;43m[异步]\e[0m测试类[$className]:$description");
            } else {
                print_r("├──\e[30;42m[同步]\e[0m测试类[$className]:$description");
            }
            if (array_key_exists('codeCoverageIgnore', $classData['@info']))//跳过代码块
            {
                print_r('->');
                $this->printIgnore();
                $this->ignoreCount += $count - 1;
                continue;
            } elseif (!$this->asyn && !array_key_exists('needTestTask', $classData['@info'])) {//同步但没有表明需要测试那就跳过
                print_r('->');
                $this->printIgnore();
                $this->ignoreCount += $count - 1;
                continue;
            } else {
                print_r(" 共 $count 个测试用例\n");
            }

            unset($classData['@info']);

            try {
                yield $classInstance->setUpBeforeClass();
            } catch (SwooleTestException $e) {
                if ($e->getCode() == SwooleTestException::ERROR) {
                    $this->printFail($e->getMessage());
                    $this->failCount += $count - 1;
                    yield $classInstance->tearDownAfterClass();
                    continue;
                } elseif ($e->getCode() == SwooleTestException::SKIP) {
                    $this->printIgnore($e->getMessage());
                    $this->ignoreCount += $count - 1;
                    yield $classInstance->tearDownAfterClass();
                    continue;
                }
            } catch (\Exception $e) {
                $this->printFail($e->getMessage());
                $this->failCount += $count - 1;
                yield $classInstance->tearDownAfterClass();
                continue;
            }

            foreach ($classData as $method => $methodInfo) {
                $description = $methodInfo['description']??'';
                if ($this->asyn) {
                    print_r("│   ├──\e[30;43m[异步]\e[0m测试方法[$method]:$description->");
                } else {
                    print_r("│   ├──\e[30;42m[同步]\e[0m测试方法[$method]:$description->");
                }
                if (array_key_exists('codeCoverageIgnore', $methodInfo)) {//跳过代码块
                    $this->printIgnore();
                    continue;
                }
                $dataProviderValues = [];
                $dataProviderValueKeys = [];
                if (array_key_exists('dataProvider', $methodInfo)) {//有数据供给器
                    $dataProviderValues = call_user_func([$classInstance, $methodInfo['dataProvider']]);
                    $dataProviderValueKeys = array_keys($dataProviderValues);
                }
                if (count($dataProviderValues) > 0) {
                    $this->totalCount += count($dataProviderValues) - 1;
                    print_r("\n");
                }
                do {
                    try {
                        yield $classInstance->setUp();
                    } catch (SwooleTestException $e) {
                        if ($e->getCode() == SwooleTestException::ERROR) {
                            $this->printFail($e->getMessage());
                            yield $classInstance->tearDown();
                            continue;
                        } elseif ($e->getCode() == SwooleTestException::SKIP) {
                            $this->printIgnore($e->getMessage());
                            yield $classInstance->tearDown();
                            continue;
                        }
                    } catch (\Exception $e) {
                        $this->printFail($e->getMessage());
                        yield $classInstance->tearDown();
                        continue;
                    }
                    //合并参数
                    $dataProviderValue = [];
                    if (count($dataProviderValues) > 0) {
                        $dataProviderValue = array_shift($dataProviderValues);
                        $dataProviderKey = array_shift($dataProviderValueKeys);
                        print_r("│   │  ├──$dataProviderKey->");
                    }

                    $parmasArray = [];
                    if (array_key_exists('depends', $methodInfo)) {//有依赖
                        if (is_array($methodInfo['depends'])) {//多个依赖
                            $error = false;
                            foreach ($methodInfo['depends'] as $methodName) {
                                $result = $classData[$methodName]['result']??null;
                                if ($result == null) {//依赖获取失败
                                    $error = true;
                                    break;
                                }
                                $parmasArray[] = $result;
                            }
                            if ($error) {
                                $this->printFail('依赖获取失败');
                                continue;
                            }
                        } else {
                            $methodName = $methodInfo['depends'];
                            $result = $classData[$methodName]['result']??null;
                            if ($result == null) {// 依赖获取失败
                                $this->printFail('依赖获取失败');
                                continue;
                            }
                            $parmasArray[] = $result;
                        }
                    }

                    $parmasArray = array_merge($dataProviderValue, $parmasArray);

                    try {
                        $result = yield call_user_func_array([$classInstance, $method], $parmasArray);
                        $classData[$method]['result'] = $result;
                        $this->printSuccess();
                    } catch (SwooleTestException $e) {
                        if ($e->getCode() == SwooleTestException::ERROR) {
                            $this->printFail($e->getMessage());
                        } elseif ($e->getCode() == SwooleTestException::SKIP) {
                            $this->printIgnore($e->getMessage());
                        }
                    } catch (\Exception $e) {
                        $this->printFail($e->getMessage());
                    }
                    yield $classInstance->tearDown();
                } while (count($dataProviderValues) != 0);
            }
            yield $classInstance->tearDownAfterClass();
            unset($this->tests[$className]);
        }
        print_r("└───总共$this->totalCount,忽略$this->ignoreCount,成功$this->successCount,失败$this->failCount\n");
        if ($this->asyn) {//异步完了再执行同步的测试
            $unitTestTask = get_instance()->loader->task('UnitTestTask');
            $unitTestTask->startTest($this->dir);
            $unitTestTask->startTask(null);
        }
    }

    public function printIgnore($message = '')
    {
        $this->ignoreCount++;
        print_r("\e[31;40m [忽略] \e[0m $message \n");
    }

    public function printFail($message = '')
    {
        $this->failCount++;
        print_r("\e[30;41m [失败] \e[0m $message \n");
    }

    public function printSuccess()
    {
        $this->successCount++;
        print_r("\e[32;40m [成功] \e[0m\n");
    }

    public function once()
    {

    }

    public function onExceptionHandle($e)
    {

    }
}

