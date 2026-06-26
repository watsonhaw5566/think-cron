# think-cron 计划任务

## 安装方法

```bash
composer require watsonhaw/think-cron
```

安装后，ThinkPHP 会自动注册服务提供者。

## 使用方法

### 1. 创建任务类

在 `app/task` 目录（或你喜欢的任意目录）下创建任务类，继承 `watsonhaw\cron\Task`：

```php
<?php

namespace app\task;

use watsonhaw\cron\Task;

class DemoTask extends Task
{
    /**
     * 配置任务的执行周期和其他选项
     */
    protected function configure()
    {
        // 每天凌晨 00:00 执行
        $this->daily();

        // 或者使用更精确的时间
        // $this->dailyAt('02:30');

        // 或者直接写 Cron 表达式
        // $this->expression('*/5 * * * *');
    }

    /**
     * 任务的实际执行逻辑
     */
    protected function execute()
    {
        // ... 具体的任务执行代码
    }
}
```

### 2. 配置任务

创建配置文件 `config/cron.php`，注册你的任务类：

```php
<?php

return [
    // 要执行的任务列表（完整类名）
    'tasks' => [
        \app\task\DemoTask::class,
        \app\task\AnotherTask::class,
    ],

    // 可选：指定缓存驱动（用于 withoutOverlapping / onOneServer 的互斥锁）
    // 默认使用 ThinkPHP 的默认缓存配置
    'store' => null,
];
```

### 3. 运行任务监听

有两种方式触发任务调度：

#### 方式一（推荐）：常驻进程方式

启动一个常驻后台进程，每分钟自动触发调度器。可配合 supervisor 使用：

```bash
php think cron:schedule
```

supervisor 配置示例：

```ini
[program:think-cron]
process_name=%(program_name)s
command=php /path/to/your/project/think cron:schedule
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/log/cron.log
```

#### 方式二：系统 crontab 方式

在服务器的系统计划任务中添加（每分钟执行一次调度器）：

```bash
* * * * * php /path/to/your/project/think cron:run >> /dev/null 2>&1
```

## 可用的周期方法

### 基本周期

```php
// 每分钟执行
$this->everyMinute();

// 每 5 分钟执行
$this->everyFiveMinutes();

// 每 10 分钟执行
$this->everyTenMinutes();

// 每 30 分钟执行
$this->everyThirtyMinutes();

// 每小时整点执行
$this->hourly();

// 每小时的第 15 分钟执行
$this->hourlyAt(15);

// 每天 00:00 执行
$this->daily();

// 每天指定时间执行
$this->dailyAt('02:30');
$this->at('02:30');       // dailyAt 的别名

// 每天指定小时的整点执行（例如：01:00 和 13:00）
$this->twiceDaily(1, 13);
```

### 按周/月/季度/年

```php
// 每周日 00:00 执行
$this->weekly();

// 每周三 10:30 执行
$this->weeklyOn(3, '10:30');

// 每月 1 日 00:00 执行
$this->monthly();

// 每月 15 日 06:20 执行
$this->monthlyOn(15, '6:20');

// 每月 1 日和 16 日 00:00 执行
$this->twiceMonthly(1, 16);

// 每季度第一天 00:00 执行
$this->quarterly();

// 每年 1 月 1 日 00:00 执行
$this->yearly();
```

### 按星期几

```php
// 工作日（周一至周五）执行
$this->weekdays();

// 周末（周六、周日）执行
$this->weekends();

// 具体某一天执行
$this->mondays();
$this->tuesdays();
$this->wednesdays();
$this->thursdays();
$this->fridays();
$this->saturdays();
$this->sundays();

// 自定义星期几（0=周日, 1=周一, ..., 6=周六）
$this->days([1, 3, 5]);
$this->days(0, 6);
```

### 自定义 Cron 表达式

```php
// 使用标准 Cron 表达式
$this->expression('30 2 * * 1-5');  // 工作日凌晨 2:30 执行
```

## 其他选项

### 时区设置

```php
$this->timezone('Asia/Shanghai');
```

### 区间时间过滤

```php
// 仅在 08:00 - 22:00 之间执行
$this->between('08:00', '22:00');

// 在 23:00 - 01:00 之间也能正确处理（跨午夜场景）
$this->between('23:00', '01:00');

// 排除特定时间区间（区间内不执行）
$this->unlessBetween('12:00', '14:00');
```

### 自定义过滤条件

```php
// 仅当条件为 true 时执行
$this->when(function () {
    return config('app.env') === 'production';
});

// 当条件为 true 时跳过
$this->skip(function () {
    return date('Y-m-d') === '2024-01-01';
});
```

### 防止任务重叠执行

```php
// 防止上一次执行未完成时，下一次又开始
// 参数为互斥锁的过期时间（秒），默认 1440 秒
$this->withoutOverlapping(1440);
```

### 多服务器环境

```php
// 仅在一台服务器上运行（通过缓存实现）
// 需要确保所有服务器使用同一个缓存实例
$this->onOneServer();
```

### 多个选项组合使用

```php
$this->dailyAt('03:00')
     ->timezone('Asia/Shanghai')
     ->withoutOverlapping()
     ->onOneServer();
```

## 事件系统

调度器会触发以下事件，你可以在 ThinkPHP 的事件系统中监听它们：

| 事件类 | 触发时机 |
|---------|---------|
| `watsonhaw\cron\event\TaskProcessed` | 任务成功执行后 |
| `watsonhaw\cron\event\TaskSkipped` | 任务被跳过时（重叠/其他服务器已执行） |
| `watsonhaw\cron\event\TaskFailed` | 任务执行抛出异常时 |

**监听示例**：

```php
use watsonhaw\cron\event\TaskProcessed;
use watsonhaw\cron\event\TaskFailed;
use watsonhaw\cron\event\TaskSkipped;

Event::listen(TaskProcessed::class, function (TaskProcessed $event) {
    $taskClass = get_class($event->task);
    // ... 记录日志、发送通知等
});

Event::listen(TaskFailed::class, function (TaskFailed $event) {
    $taskClass = get_class($event->task);
    $exception = $event->exception;
    // ... 发送告警
});

Event::listen(TaskSkipped::class, function (TaskSkipped $event) {
    $taskClass = get_class($event->task);
    $reason    = $event->reason;  // 'overlapping' 或 'single_server'
    // ...
});
```

使用 `cron:schedule` 命令运行时，控制台会自动输出这些事件信息。

## 完整示例

```php
<?php

namespace app\task;

use watsonhaw\cron\Task;

class DailyReportTask extends Task
{
    protected function configure()
    {
        // 每天凌晨 2 点（上海时区）执行
        $this->dailyAt('02:00')
             ->timezone('Asia/Shanghai')
             ->withoutOverlapping()     // 防止重叠
             ->onOneServer();           // 仅一台服务器执行
    }

    protected function execute()
    {
        // 生成每日报表并发送邮件
        $report = $this->generateReport();
        $this->sendReportEmail($report);
    }

    private function generateReport() { /* ... */ }
    private function sendReportEmail($report) { /* ... */ }
}
```

## 注意事项

1. **`withoutOverlapping` 和 `onOneServer` 依赖缓存系统** — 建议使用 Redis 等共享缓存驱动，避免文件缓存导致多进程状态不一致。
2. **`cron:schedule` 为常驻进程** — 建议使用 supervisor 管理，确保进程意外退出后能自动重启。
3. **时区影响** — 如果设置了 `timezone`，会以该时区的时间判断是否到期，注意与服务器系统时区的差异。
4. **事件触发** — 任务 `execute` 方法中抛出的异常会被捕获并触发 `TaskFailed` 事件，不会中断调度器运行。