<?php
/**
 * Model 涉及到数据有关的处理
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Models;

use PG\MSF\Base\Core;

class Model extends Core
{
    final public function __construct()
    {
        parent::__construct();
    }

    /**
     * 当被loader时会调用这个方法进行初始化
     * @param \PG\MSF\Helpers\Context $context
     */
    public function initialization($context)
    {
        $this->setContext($context);
    }

    /**
     * 销毁回归对象池
     */
    public function destroy()
    {
        parent::destroy();
        Factory::getInstance()->revertModel($this);
    }
}
