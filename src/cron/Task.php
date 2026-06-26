<?php

namespace yunwuxin\cron;

use Closure;
use Cron\CronExpression;
use think\App;
use think\Cache;

abstract class Task
{

    use ManagesFrequencies;

    /** @var string|null 时区 */
    public $timezone = null;

    /** @var string 任务周期 */
    public $expression = '* * * * *';

    /** @var bool 任务是否可以重叠执行 */
    public $withoutOverlapping = false;

    /** @var int 互斥锁过期秒数(重叠执行检查用,避免锁残留导致任务永不执行) */
    public $expiresAt = 1440;

    /** @var bool 分布式部署 是否仅在一台服务器上运行 */
    public $onOneServer = false;

    protected $filters = [];
    protected $rejects = [];

    /** @var Cache */
    protected $cache;

    /** @var App */
    protected $app;

    public function __construct(App $app, Cache $cache)
    {
        $this->app   = $app;
        $this->cache = $cache;
        $this->configure();
    }

    /**
     * 是否到期执行
     * @return bool
     */
    public function isDue()
    {
        $cronExpression = new CronExpression($this->expression);

        return $cronExpression->isDue('now', $this->timezone);
    }

    /**
     * 配置任务
     */
    protected function configure()
    {
    }

    /**
     * 执行任务
     */
    protected function execute()
    {
        $this->app->invoke([$this, 'handle'], [], true);
    }

    final public function run()
    {
        if ($this->withoutOverlapping &&
            !$this->createMutex()) {
            return;
        }

        register_shutdown_function(function () {
            $this->removeMutex();
        });

        try {
            $this->execute();
        } finally {
            $this->removeMutex();
        }
    }

    /**
     * 过滤
     * @return bool
     */
    public function filtersPass()
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
    public function mutexName()
    {
        return 'task-' . sha1(static::class);
    }

    protected function removeMutex(): bool
    {
        return $this->cache->delete($this->mutexName());
    }

    /**
     * 创建互斥锁
     * 注意: 避免 has+set 两步操作造成 TOCTOU 竞态,
     * 直接依赖 set 返回值作为是否成功的判断依据。
     */
    protected function createMutex(): bool
    {
        $name = $this->mutexName();
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

    public function when(Closure $callback)
    {
        $this->filters[] = $callback;

        return $this;
    }

    public function skip(Closure $callback)
    {
        $this->rejects[] = $callback;

        return $this;
    }

    public function withoutOverlapping($expiresAt = 1440)
    {
        $this->withoutOverlapping = true;

        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function onOneServer()
    {
        $this->onOneServer = true;

        return $this;
    }
}