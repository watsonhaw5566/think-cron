<?php

namespace yunwuxin\cron\tests;

use Carbon\Carbon;
use Exception;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Cache;
use think\Event;
use yunwuxin\cron\event\TaskFailed;
use yunwuxin\cron\event\TaskProcessed;
use yunwuxin\cron\event\TaskSkipped;
use yunwuxin\cron\Scheduler;
use yunwuxin\cron\Task;

/**
 * 内存 cache：所有 cache 操作都在内存数组中，便于测试共享状态
 */
class MemoryCache extends Cache
{
    public array $data = [];

    public function __construct()
    {
        // 跳过父类构造（不需要真实 App）
    }

    public function has($key): bool
    {
        return isset($this->data[$key]);
    }

    public function get($key, $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->data[$key] = $value;
        return true;
    }

    public function delete($key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    public function store($name = null): self
    {
        return $this;
    }
}

/**
 * 配置对象：提供给 Scheduler 的 cron.tasks 和 cron.store 配置
 */
class TestConfig
{
    public function __construct(private array $tasks, private ?string $store = null)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'cron.tasks' => $this->tasks,
            'cron.store' => $this->store,
            default      => $default,
        };
    }
}

/**
 * 事件监听器：记录所有触发的事件供断言使用
 */
class TestEvent
{
    public array $triggered = [];

    public function trigger($event, ...$params): void
    {
        $this->triggered[] = is_object($event) ? get_class($event) : $event;
    }

    public function until($event, ...$params): void
    {
        $this->trigger($event, ...$params);
    }

    public function listen($events, $listener = null): void
    {
    }
}

// === 各种 Task 子类（供不同测试场景使用） ===

class AlwaysDueTask extends Task
{
    public $expression = '* * * * *';
    public static bool $handleCalled = false;

    protected function configure(): void
    {
    }

    public function handle(): void
    {
        self::$handleCalled = true;
    }
}

class NeverDueTask extends Task
{
    public $expression = '0 0 1 1 *'; // 1月1日 00:00，永远不会到期
    public static bool $handleCalled = false;

    protected function configure(): void
    {
    }

    public function handle(): void
    {
        self::$handleCalled = true;
    }
}

class FilteredTask extends Task
{
    public $expression = '* * * * *';
    public static bool $handleCalled = false;

    protected function configure(): void
    {
        $this->skip(static fn () => true);
    }

    public function handle(): void
    {
        self::$handleCalled = true;
    }
}

class FailingTask extends Task
{
    public $expression = '* * * * *';

    protected function configure(): void
    {
    }

    public function handle(): void
    {
        throw new Exception('task failed');
    }
}

class SingleServerTask extends Task
{
    public $expression = '* * * * *';
    public $onOneServer = true;
    public static bool $handleCalled = false;

    protected function configure(): void
    {
    }

    public function handle(): void
    {
        self::$handleCalled = true;
    }

    public function mutexName(): string
    {
        return 'single-server-mutex';
    }
}

/**
 * 时间区间任务：仅在 10:00-14:00 运行（不跨午夜）
 */
class BetweenTask extends Task
{
    public $expression = '* * * * *';
    public static bool $handleCalled = false;

    protected function configure(): void
    {
        $this->between('10:00', '14:00');
    }

    public function handle(): void
    {
        self::$handleCalled = true;
    }
}

/**
 * 跨午夜时间区间任务：仅在 23:00-01:00 运行
 */
class BetweenOvernightTask extends Task
{
    public $expression = '* * * * *';
    public static bool $handleCalled = false;

    protected function configure(): void
    {
        $this->between('23:00', '01:00');
    }

    public function handle(): void
    {
        self::$handleCalled = true;
    }
}

/**
 * 排除区间任务：除非处于 12:00-14:00，否则都应该运行
 */
class UnlessBetweenTask extends Task
{
    public $expression = '* * * * *';
    public static bool $handleCalled = false;

    protected function configure(): void
    {
        $this->unlessBetween('12:00', '14:00');
    }

    public function handle(): void
    {
        self::$handleCalled = true;
    }
}

final class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        // 重置所有静态标志
        AlwaysDueTask::$handleCalled       = false;
        NeverDueTask::$handleCalled        = false;
        FilteredTask::$handleCalled        = false;
        SingleServerTask::$handleCalled    = false;
        BetweenTask::$handleCalled         = false;
        BetweenOvernightTask::$handleCalled = false;
        UnlessBetweenTask::$handleCalled   = false;

        // 清除 Carbon 的时间 mock（其他测试可能留下的状态）
        Carbon::setTestNow(null);
    }

    protected function tearDown(): void
    {
        // 测试结束后务必清除 mock，避免影响其他测试
        Carbon::setTestNow(null);
    }

    /**
     * 创建一个配置好的真实 ThinkPHP App
     *
     * @param array  $taskClasses 任务类名数组
     * @param MemoryCache $sharedCache 共享的内存 cache
     * @param TestEvent|null $event 事件监听器
     */
    private function makeApp(array $taskClasses, MemoryCache $sharedCache, ?TestEvent $event = null): App
    {
        $app = new App();
        $app->instance('config', new TestConfig($taskClasses));
        $app->instance('cache', $sharedCache);
        $app->instance(Event::class, $event ?? new TestEvent());

        return $app;
    }

    public function test_it_skips_non_due_and_filtered_tasks(): void
    {
        $event = new TestEvent();
        $app   = $this->makeApp([NeverDueTask::class, FilteredTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertFalse(NeverDueTask::$handleCalled);
        self::assertFalse(FilteredTask::$handleCalled);
        self::assertEmpty($event->triggered);
    }

    public function test_it_runs_a_task_and_triggers_processed_event(): void
    {
        $event = new TestEvent();
        $app   = $this->makeApp([AlwaysDueTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertTrue(AlwaysDueTask::$handleCalled);
        self::assertContains(TaskProcessed::class, $event->triggered);
    }

    public function test_it_triggers_failed_event_when_task_throws(): void
    {
        $event = new TestEvent();
        $app   = $this->makeApp([FailingTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertContains(TaskFailed::class, $event->triggered);
    }

    public function test_it_runs_only_on_one_server_when_flag_is_set(): void
    {
        $event = new TestEvent();
        $app   = $this->makeApp([SingleServerTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertTrue(SingleServerTask::$handleCalled);
        self::assertContains(TaskProcessed::class, $event->triggered);
    }

    public function test_it_skips_task_on_other_server_if_mutex_exists(): void
    {
        $sharedCache = new MemoryCache();

        // 预先写入 Scheduler 会检查的 key（模仿其他服务器已运行）
        $sharedCache->data['single-server-mutex' . date('Hi')] = true;

        $event = new TestEvent();
        $app   = $this->makeApp([SingleServerTask::class], $sharedCache, $event);

        (new Scheduler($app))->run();

        self::assertFalse(SingleServerTask::$handleCalled);
        self::assertContains(TaskSkipped::class, $event->triggered);
    }

    // === between/unlessBetween 时间区间过滤测试 ===

    public function test_between_allows_task_within_interval(): void
    {
        Carbon::setTestNow('2024-01-01 12:00:00');

        $event = new TestEvent();
        $app   = $this->makeApp([BetweenTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertTrue(BetweenTask::$handleCalled);
        self::assertContains(TaskProcessed::class, $event->triggered);
    }

    public function test_between_blocks_task_before_interval(): void
    {
        Carbon::setTestNow('2024-01-01 09:00:00');

        $event = new TestEvent();
        $app   = $this->makeApp([BetweenTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertFalse(BetweenTask::$handleCalled);
        self::assertEmpty($event->triggered);
    }

    public function test_between_blocks_task_after_interval(): void
    {
        Carbon::setTestNow('2024-01-01 15:00:00');

        $event = new TestEvent();
        $app   = $this->makeApp([BetweenTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertFalse(BetweenTask::$handleCalled);
        self::assertEmpty($event->triggered);
    }

    public function test_between_supports_overnight_interval_runs_late_night(): void
    {
        // 23:00-01:00 区间：在 23:30（区间内）应允许
        Carbon::setTestNow('2024-01-01 23:30:00');

        $event = new TestEvent();
        $app   = $this->makeApp([BetweenOvernightTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertTrue(BetweenOvernightTask::$handleCalled);
        self::assertContains(TaskProcessed::class, $event->triggered);
    }

    public function test_between_supports_overnight_interval_runs_after_midnight(): void
    {
        // 23:00-01:00 区间：在 00:30（跨零点后，仍在区间内）应允许
        Carbon::setTestNow('2024-01-02 00:30:00');

        $event = new TestEvent();
        $app   = $this->makeApp([BetweenOvernightTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertTrue(BetweenOvernightTask::$handleCalled);
        self::assertContains(TaskProcessed::class, $event->triggered);
    }

    public function test_between_blocks_outside_overnight_interval(): void
    {
        // 23:00-01:00 区间：在 22:00（区间之前）应拒绝
        Carbon::setTestNow('2024-01-01 22:00:00');

        $event = new TestEvent();
        $app   = $this->makeApp([BetweenOvernightTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertFalse(BetweenOvernightTask::$handleCalled);
        self::assertEmpty($event->triggered);
    }

    public function test_between_blocks_outside_overnight_interval_after_window(): void
    {
        // 23:00-01:00 区间：在 02:00（区间之后）应拒绝
        Carbon::setTestNow('2024-01-02 02:00:00');

        $event = new TestEvent();
        $app   = $this->makeApp([BetweenOvernightTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertFalse(BetweenOvernightTask::$handleCalled);
        self::assertEmpty($event->triggered);
    }

    public function test_unless_between_runs_outside_interval(): void
    {
        // unlessBetween(12:00,14:00)：在 10:00（区间外）应运行
        Carbon::setTestNow('2024-01-01 10:00:00');

        $event = new TestEvent();
        $app   = $this->makeApp([UnlessBetweenTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertTrue(UnlessBetweenTask::$handleCalled);
        self::assertContains(TaskProcessed::class, $event->triggered);
    }

    public function test_unless_between_skips_within_interval(): void
    {
        // unlessBetween(12:00,14:00)：在 13:00（区间内）应跳过
        Carbon::setTestNow('2024-01-01 13:00:00');

        $event = new TestEvent();
        $app   = $this->makeApp([UnlessBetweenTask::class], new MemoryCache(), $event);

        (new Scheduler($app))->run();

        self::assertFalse(UnlessBetweenTask::$handleCalled);
        self::assertEmpty($event->triggered);
    }
}