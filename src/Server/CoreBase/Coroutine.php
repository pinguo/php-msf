<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午5:07
 */

namespace Server\CoreBase;


class Coroutine
{
    /**
     * 可以根据需要更改定时器间隔，单位ms
     **/
    const TICK_INTERVAL = 1;

    private $routineList;

    private $tickId = -1;

    private $tickTime = 0;

    public function __construct()
    {
        $this->routineList = [];
        $this->startTick();
    }

    public function start(\Generator $routine, GeneratorContext $generatorContext)
    {
        $task = new CoroutineTask($routine, $generatorContext);
        $this->routineList[] = $task;
    }

    /**
     * 服务器运行到现在的毫秒数
     * @return int
     */
    public function getTickTime()
    {
        return $this->tickTime*self::TICK_INTERVAL;
    }

    private function startTick()
    {
        swoole_timer_tick(self::TICK_INTERVAL, function ($timerId) {
            $this->tickTime++;
            $this->tickId = $timerId;
            $this->run();
            get_instance()->tickTime = $this->getTickTime();
        });
    }

    private function run()
    {
        if (empty($this->routineList)) {
            return;
        }

        foreach ($this->routineList as $k => $task) {
            $task->run();

            if ($task->isFinished()) {
                $task->destory();
                unset($this->routineList[$k]);
            }
        }
    }

    public function stop(\Generator $routine)
    {
        foreach ($this->routineList as $k => $task) {
            if ($task->getRoutine() == $routine) {
                unset($this->routineList[$k]);
            }
        }
    }
}