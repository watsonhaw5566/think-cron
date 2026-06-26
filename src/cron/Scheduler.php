<?php

namespace yunwuxin\cron;

use Carbon\Carbon;
use Throwable;
use think\App;
use think\cache\Driver;
use yunwuxin\cron\event\TaskFailed;
use yunwuxin\cron\event\TaskProcessed;
use yunwuxin\cron\event\TaskSkipped;

class Scheduler
{
    /** @var App */
    protected $app;

    /** @var Carbon */
    protected $startedAt;

    protected $tasks = [];

    /** @var Driver */
    protected $cache;

    public function __construct(App $app)
    {
        $this->app   = $app;
        $this->tasks = $app->config->get('cron.tasks', []);
        $this->cache = $app->cache->store($app->config->get('cron.store', null));
    }

    public function run()
    {
        $this->startedAt = Carbon::now();
        foreach ($this->tasks as $taskClass) {

            if (is_string($taskClass) && class_exists($taskClass) && is_subclass_of($taskClass, Task::class)) {

                /** @var Task $task */
                $task = $this->app->invokeClass($taskClass, [$this->app, $this->cache]);
                if ($task->isDue()) {

                    if (!$task->filtersPass()) {
                        continue;
                    }

                    $ran = $task->onOneServer
                        ? $this->runSingleServerTask($task)
                        : $this->runTask($task);

                    if ($ran) {
                        $this->app->event->trigger(new TaskProcessed($task));
                    }
                }
            }
        }
    }

    /**
     * @param Task $task
     * @return bool
     */
    protected function serverShouldRun($task): bool
    {
        $key = $task->mutexName() . $this->startedAt->format('Hi');

        if ($this->cache->has($key)) {
            return false;
        }

        return $this->cache->set($key, true, 120);
    }

    /**
     * @param Task $task
     * @return bool 任务是否实际执行（false 表示被跳过）
     */
    protected function runSingleServerTask(Task $task): bool
    {
        if ($this->serverShouldRun($task)) {
            return $this->runTask($task);
        }

        $this->app->event->trigger(new TaskSkipped($task));
        return false;
    }

    /**
     * @param Task $task
     * @return bool 任务是否成功执行（异常或 withoutOverlapping 跳过时返回 false）
     */
    protected function runTask(Task $task): bool
    {
        try {
            return $task->run();
        } catch (Throwable $e) {
            $this->app->event->trigger(new TaskFailed($task, $e));
            return false;
        }
    }
}