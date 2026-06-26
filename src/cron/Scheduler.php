<?php

namespace watsonhaw\cron;

use Carbon\Carbon;
use Throwable;
use think\App;
use think\Cache;
use watsonhaw\cron\event\TaskFailed;
use watsonhaw\cron\event\TaskProcessed;
use watsonhaw\cron\event\TaskSkipped;

class Scheduler
{
    protected App $app;

    protected Carbon $startedAt;

    /** @var list<class-string<Task>> */
    protected array $tasks = [];

    protected Cache $cache;

    public function __construct(App $app)
    {
        $this->app   = $app;
        $this->tasks = $app->config->get('cron.tasks', []);
        $this->cache = $app->cache->store($app->config->get('cron.store', null));
    }

    /**
     * 返回所有已注册的任务实例列表。
     * 配置中类型不合法（非 Task 子类）的条目会被静默跳过。
     *
     * @return list<Task>
     */
    public function getTasks(): array
    {
        $tasks = [];
        foreach ($this->tasks as $taskClass) {
            if (is_string($taskClass) && class_exists($taskClass) && is_subclass_of($taskClass, Task::class)) {
                $tasks[] = $this->app->invokeClass($taskClass, [$this->app, $this->cache]);
            }
        }
        return $tasks;
    }

    public function run(): void
    {
        $this->startedAt = Carbon::now();
        foreach ($this->tasks as $taskClass) {

            if (is_string($taskClass) && class_exists($taskClass) && is_subclass_of($taskClass, Task::class)) {

                /** @var Task $task */
                $task = $this->app->invokeClass($taskClass, [$this->app, $this->cache]);

                try {
                    if (!$task->isDue()) {
                        continue;
                    }

                    if (!$task->filtersPass()) {
                            continue;
                    }
                } catch (Throwable $e) {
                    $this->app->event->trigger(new TaskFailed($task, $e));
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

    protected function serverShouldRun(Task $task): bool
    {
        $key = $task->mutexName() . $this->startedAt->format('Hi');

        if ($this->cache->has($key)) {
            return false;
        }

        return $this->cache->set($key, true, 120);
    }

    /**
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