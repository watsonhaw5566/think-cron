<?php

namespace watsonhaw\cron;

use Closure;
use Cron\CronExpression;
use think\App;
use think\Cache;
use watsonhaw\cron\event\TaskSkipped;

abstract class Task
{

    use ManagesFrequencies;

    /** @var string|null 时区 */
    public ?string $timezone = null;

    /** @var string 任务周期 */
    public string $expression = '* * * * *';

    /** @var bool 任务是否可以重叠执行 */
    public bool $withoutOverlapping = false;

    /** @var int 互斥锁过期秒数(重叠执行检查用,避免锁残留导致任务永不执行) */
    public int $expiresAt = 1440;

    /** @var bool|null 分布式部署：null=未设置（继承全局配置）；true/false=显式覆盖 */
    public ?bool $onOneServer = null;

    /** @var list<callable(): bool> */
    protected array $filters = [];

    /** @var list<callable(): bool> */
    protected array $rejects = [];

    protected Cache $cache;

    protected App $app;

    public function __construct(App $app, Cache $cache)
    {
        $this->app   = $app;
        $this->cache = $cache;
        $this->configure();
    }

    /**
     * 是否到期执行
     */
    public function isDue(): bool
    {
        $cronExpression = new CronExpression($this->expression);

        return $cronExpression->isDue('now', $this->timezone);
    }

    /**
     * 配置任务
     */
    protected function configure(): void
    {
    }

    /**
     * 执行任务（子类必须实现此方法）
     */
    abstract protected function execute(): void;

    /**
     * @return bool 任务是否真正执行（被 withoutOverlapping 跳过时返回 false）
     */
    final public function run(): bool
    {
        if ($this->withoutOverlapping &&
            !$this->createMutex()) {
            $this->app->event->trigger(new TaskSkipped($this, 'overlapping'));
            return false;
        }

        try {
            $this->app->invoke([$this, 'execute'], [], true);
        } finally {
            if ($this->withoutOverlapping) {
                $this->removeMutex();
            }
        }

        return true;
    }

    /**
     * 过滤
     */
    public function filtersPass(): bool
    {
        foreach ($this->filters as $callback) {
            if (!call_user_func($callback)) {
                return false;
            }
        }

        foreach ($this->rejects as $callback) {
            if (call_user_func($callback)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 任务标识
     */
    public function mutexName(): string
    {
        return 'task-' . sha1(static::class);
    }

    protected function removeMutex(): bool
    {
        return $this->cache->delete($this->mutexName());
    }

    /**
     * 创建互斥锁，防止任务重叠执行。
     * 必须先 has() 判断：ThinkPHP 的 set() 会无条件覆盖已有值，
     * 没有 has() 的话互斥检查完全失效。
     * 注意：多进程/多服务器下仍存在 TOCTOU 竞态窗口，如需真正的原子互斥，
     * 请使用支持 SETNX 的缓存驱动（如 Redis）。
     */
    protected function createMutex(): bool
    {
        $name = $this->mutexName();
        if ($this->cache->has($name)) {
            return false;
        }
        return $this->cache->set($name, time(), $this->expiresAt);
    }

    protected function existsMutex(): bool
    {
        $mutex = $this->cache->get($this->mutexName());
        if ($mutex === null) {
            return false;
        }
        return (int) $mutex + $this->expiresAt > time();
    }

    /**
     * @return $this
     */
    public function when(Closure $callback): static
    {
        $this->filters[] = $callback;

        return $this;
    }

    /**
     * @return $this
     */
    public function skip(Closure $callback): static
    {
        $this->rejects[] = $callback;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutOverlapping(int $expiresAt = 1440): static
    {
        $this->withoutOverlapping = true;

        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * @return $this
     */
    public function onOneServer(): static
    {
        $this->onOneServer = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutOnOneServer(): static
    {
        $this->onOneServer = false;

        return $this;
    }
}