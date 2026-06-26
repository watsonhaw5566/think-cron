# think-cron 计划任务

适用于 ThinkPHP 8 的计划任务（定时任务）扩展，支持丰富的周期表达、单服务器执行、防重叠执行、事件系统以及 think-swoole 集成。

## 安装方法

```
composer require watsonhaw/think-cron
```

安装完成后会自动注册三个命令：`cron:run`、`cron:schedule` 与 `cron:show`。

## 使用方法

### 创建任务类

继承 `watsonhaw\cron\Task`，在 `configure()` 中设置执行周期，在 `execute()` 中实现业务逻辑：

```php
<?php

namespace app\task;

use watsonhaw\cron\Task;

class DemoTask extends Task
{
    /**
     * 配置任务的执行周期与行为（构造时自动调用一次）
     */
    protected function configure()
    {
        // 每天凌晨 00:00 执行
        $this->daily();
    }

    /**
     * 执行任务的业务逻辑
     */
    protected function execute()
    {
        // ...具体的任务执行
    }
}
```

### 配置

配置文件位于 `config/cron.php`：

```php
return [
    // 任务的完整类名列表
    'tasks' => [
        \app\task\DemoTask::class,
    ],

    // （可选）使用的缓存驱动，用于互斥锁/单服务器判断；留空使用默认缓存
    // 推荐使用 Redis 等支持 SETNX 的驱动以获得真正的原子互斥
    'store' => null,

    // （可选）全局默认：所有任务是否仅在一台服务器上运行（默认 false）
    // 设为 true 后，所有未显式覆盖的任务都会走单服务器流程，
    // 避免在每个任务的 configure() 中重复调用 ->onOneServer()
    // 任务级的 ->onOneServer() / ->withoutOnOneServer() 始终优先于此配置
    'onOneServer' => false,
];
```

### 任务监听

提供三种运行方式，可根据部署场景任选其一。

#### 方法一：常驻进程（推荐）

`cron:schedule` 会每分钟自动执行一次 `cron:run`，可配合 supervisor 等工具守护运行：

```
php think cron:schedule
```

启动后会看到提示：

```
Cron schedule started. Press Ctrl+C to stop.
```

当 `cron:run` 异常退出时会在终端打印退出码，便于排查问题。

#### 方法二：系统 Cron

在系统计划任务中添加，由系统层面每分钟触发：

```
* * * * * php /path/to/think cron:run >> /dev/null 2>&1
```

#### 方法三：Swoole 常驻（可选）

如果项目已安装 `topthink/think-swoole`，扩展会在 `swoole.init` 事件中自动注册一个独立的 `cron` Worker，使用
`Swoole\Timer::tick` 每 60 秒运行一次调度器，无需额外配置。

### 查看已注册任务

开发调试时可用 `cron:show` 查看所有已注册任务及其配置：

```
php think cron:show
```

输出示例 （Task=任务类, Expression=Cron 表达式, Next Run=下次运行时间, Timezone=时区, Without Overlap=防止重叠执行, On One Server=仅在单台服务器运行）：

```
+--------------------------+-----------+---------------------+-----------+----------------+---------------+
| Task                     | Expression| Next Run            | Timezone  | Without Overlap| On One Server |
+--------------------------+-----------+---------------------+-----------+----------------+---------------+
| app\task\DemoTask        | 0 0 * * * | 2026-06-27 00:00:00 | (default) | Yes            | No            |
| app\task\HourlyTask      | 0 * * * * | 2026-06-26 15:00:00 | (default) | No             | Yes           |
+--------------------------+-----------+---------------------+-----------+----------------+---------------+
2 task(s) total.
```

> 如果尚未在 `config/cron.php` 中注册任何任务，会显示 `No scheduled tasks registered.`

---

## 任务周期（频率）设置

`configure()` 中可通过以下方法链式调用设置执行周期（方法均返回 `$this`，支持链式调用）：

| 方法                                                   | 说明                                 |
|------------------------------------------------------|------------------------------------|
| `->everyMinute()`                                    | 每分钟执行                              |
| `->everyFiveMinutes()`                               | 每 5 分钟执行                           |
| `->everyTenMinutes()`                                | 每 10 分钟执行                          |
| `->everyThirtyMinutes()`                             | 每 30 分钟执行                          |
| `->hourly()`                                         | 每小时整点执行                            |
| `->hourlyAt($offset)`                                | 每小时的第 `$offset` 分钟执行               |
| `->daily()` / `->dailyAt('13:00')` / `->at('13:00')` | 每天 / 指定时间执行                        |
| `->twiceDaily(1, 13)`                                | 每天执行两次（01:00 与 13:00）              |
| `->weekdays()` / `->weekends()`                      | 工作日 / 周末执行                         |
| `->mondays()` … `->sundays()`                        | 指定周几执行                             |
| `->days(1, 3, 5)` 或 `->days([1, 3, 5])`              | 指定每周的若干天执行（0=周日 … 6=周六）            |
| `->weekly()` / `->weeklyOn(1, '8:00')`               | 每周 / 每周指定日指定时间                     |
| `->monthly()` / `->monthlyOn(4, '15:00')`            | 每月 / 每月指定日指定时间                     |
| `->twiceMonthly(1, 16)`                              | 每月执行两次（1 号与 16 号）                  |
| `->quarterly()`                                      | 每季度执行                              |
| `->yearly()`                                         | 每年执行                               |
| `->expression('0 */2 * * *')`                        | 使用原生 Cron 表达式                      |
| `->timezone('Asia/Shanghai')`                        | 设置任务时区（不设置则使用系统默认）                 |
| `->between('22:00', '01:00')`                        | 仅在指定时间区间内执行（支持跨午夜，如 23:00 → 01:00） |
| `->unlessBetween('00:00', '06:00')`                  | 在指定时间区间内**跳过**执行                   |

示例：

```php
protected function configure()
{
    // 工作日每天 09:30 执行，且仅在 09:00 - 18:00 区间内才会生效
    $this->weekdays()->at('09:30')->between('09:00', '18:00');
}
```

---

## 任务控制

> **分布式部署须启用 Redis**：在多台服务器部署时，必须在 `config/cron.php` 中配置 `store` 为 Redis
> 驱动，否则跨服务器任务控制将失效，多台机无法防止重叠执行或器同时执行同一任务。

### 防止重叠执行 `withoutOverlapping`

使用缓存（推荐 Redis）作为互斥锁，避免上一次尚未结束时新的一次又被触发。

```php
$this->daily()
     ->withoutOverlapping(); // 默认为锁 1440 分钟（24 小时）过期

// 或自定义锁过期秒数
$this->hourly()->withoutOverlapping(60);
```

### 仅在一台服务器运行 `onOneServer`

分布式部署时，让任务在多台机器中只执行一次。支持**全局默认 + 任务级显式覆盖**两种方式：

**方式一：在 `config/cron.php` 中全局开启（推荐）**

```php
'onOneServer' => true,  // 所有任务默认仅在一台服务器运行
```

开启后无需在每个任务中重复调用 `->onOneServer()`。

**方式二：在任务的 `configure()` 中按任务控制**

```php
// 强制此任务在单服务器执行（即使全局 onOneServer = false）
$this->hourly()->onOneServer();

// 强制此任务在所有服务器并行执行（即使全局 onOneServer = true）
// 适用于：清理本机临时目录、收集本机监控数据等需要每台机器都执行的场景
$this->hourly()->withoutOnOneServer();
```

**优先级规则**：任务级设置始终优先于全局配置：

| 任务 `$onOneServer` | 全局 `onOneServer` | 最终行为 |
|--------------------|-------------------|---------|
| `null`（未设置）   | `false`           | 多服务器并行（默认） |
| `null`（未设置）   | `true`            | 单服务器执行 |
| `true`（`->onOneServer()`） | 任意 | **单服务器执行**（任务级优先） |
| `false`（`->withoutOnOneServer()`） | 任意 | **多服务器并行**（任务级优先） |

**机制说明**：以「任务名 + 当前时间 HHMM」为键写入缓存，写入成功的那台服务器执行，其余机器通过 `TaskSkipped` 事件跳过。

### 条件执行 `when` / `skip`

根据运行时条件动态决定是否执行：

```php
$this->hourly()
     ->when(function () {
         return config('app.feature_enabled');
     })
     ->skip(function () {
         return date('Y-m-d') === '2026-01-01';
     });
```

---

## 事件系统

任务运行过程中会触发以下事件，可在应用中通过 `Event::listen` 或服务绑定订阅：

| 事件类                                  | 说明                  | 可用属性                                                             |
|--------------------------------------|---------------------|------------------------------------------------------------------|
| `watsonhaw\cron\event\TaskProcessed` | 任务成功执行后             | `$event->task`（任务实例）、`$event->getName()`（任务类名）                   |
| `watsonhaw\cron\event\TaskSkipped`   | 任务被跳过时（单服务器 / 重叠执行） | `$event->task`、`$event->reason`（`single_server` 或 `overlapping`） |
| `watsonhaw\cron\event\TaskFailed`    | 任务执行抛出异常时           | `$event->task`、`$event->exception`                               |

示例：

```php
// 在应用的 Event 服务中订阅
Event::listen(\watsonhaw\cron\event\TaskFailed::class, function ($event) {
    // 记录日志 / 发送告警等
    logger('cron')->error('Task failed: ' . $event->getName(), [
        'exception' => $event->exception->getMessage(),
    ]);
});
```

> `cron:run` 命令内部已默认订阅这三类事件，会在控制台输出相应提示并通过框架的 `Handle` 上报异常。

---

## 命令速查

| 命令                        | 说明                                 |
|---------------------------|------------------------------------|
| `php think cron:run`      | 单次扫描并执行所有到期任务                      |
| `php think cron:schedule` | 常驻进程，每分钟调用一次 `cron:run`（Ctrl+C 停止） |
| `php think cron:show`     | 以表格形式查看当前已注册的所有计划任务及其运行配置          |