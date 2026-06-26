<?php

namespace watsonhaw\cron;

use Carbon\Carbon;
use Closure;

trait ManagesFrequencies
{
    /**
     * 设置任务执行周期
     *
     * @return $this
     */
    public function expression(string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * 设置区间时间
     *
     * @return $this
     */
    public function between(string $startTime, string $endTime): static
    {
        return $this->when($this->inTimeInterval($startTime, $endTime));
    }

    /**
     * 排除区间时间
     *
     * @return $this
     */
    public function unlessBetween(string $startTime, string $endTime): static
    {
        return $this->skip($this->inTimeInterval($startTime, $endTime));
    }

    /**
     * @return Closure(): bool
     */
    private function inTimeInterval(string $startTime, string $endTime): Closure
    {
        return function () use ($startTime, $endTime) {
            $now   = Carbon::now($this->timezone);
            $start = Carbon::parse($startTime, $this->timezone);
            $end   = Carbon::parse($endTime, $this->timezone);

            // 跨午夜场景：start(23:00) > end(01:00) 表示区间跨越当天零点
            // 应理解为「now >= 23:00 或 now <= 01:00」
            if ($start->gt($end)) {
                return $now->gte($start) || $now->lte($end);
            }

            return $now->between($start, $end, true);
        };
    }

    /**
     * 按小时执行
     *
     * @return $this
     */
    public function hourly(): static
    {
        return $this->spliceIntoPosition(1, 0);
    }

    /**
     * 按小时延期执行
     *
     * @return $this
     */
    public function hourlyAt(int $offset): static
    {
        return $this->spliceIntoPosition(1, $offset);
    }

    /**
     * 按天执行
     *
     * @return $this
     */
    public function daily(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0);
    }

    /**
     * 指定时间执行
     *
     * @return $this
     */
    public function at(string $time): static
    {
        return $this->dailyAt($time);
    }

    /**
     * 指定时间执行
     *
     * @return $this
     */
    public function dailyAt(string $time): static
    {
        $segments = explode(':', $time);

        return $this->spliceIntoPosition(2, (int) $segments[0])
            ->spliceIntoPosition(1, count($segments) === 2 ? (int) $segments[1] : 0);
    }

    /**
     * 每天执行两次
     *
     * @return $this
     */
    public function twiceDaily(int $first = 1, int $second = 13): static
    {
        $hours = $first . ',' . $second;

        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, $hours);
    }

    /**
     * 工作日执行
     *
     * @return $this
     */
    public function weekdays(): static
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    /**
     * 周末执行
     *
     * @return $this
     */
    public function weekends(): static
    {
        return $this->spliceIntoPosition(5, '0,6');
    }

    /**
     * 星期一执行
     *
     * @return $this
     */
    public function mondays(): static
    {
        return $this->days(1);
    }

    /**
     * 星期二执行
     *
     * @return $this
     */
    public function tuesdays(): static
    {
        return $this->days(2);
    }

    /**
     * 星期三执行
     *
     * @return $this
     */
    public function wednesdays(): static
    {
        return $this->days(3);
    }

    /**
     * 星期四执行
     *
     * @return $this
     */
    public function thursdays(): static
    {
        return $this->days(4);
    }

    /**
     * 星期五执行
     *
     * @return $this
     */
    public function fridays(): static
    {
        return $this->days(5);
    }

    /**
     * 星期六执行
     *
     * @return $this
     */
    public function saturdays(): static
    {
        return $this->days(6);
    }

    /**
     * 星期天执行
     *
     * @return $this
     */
    public function sundays(): static
    {
        return $this->days(0);
    }

    /**
     * 按周执行
     *
     * @return $this
     */
    public function weekly(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(5, 0);
    }

    /**
     * 指定每周的时间执行
     *
     * @return $this
     */
    public function weeklyOn(int $day, string $time = '0:0'): static
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(5, $day);
    }

    /**
     * 按月执行
     *
     * @return $this
     */
    public function monthly(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1);
    }

    /**
     * 指定每月的执行时间
     *
     * @return $this
     */
    public function monthlyOn(int $day = 1, string $time = '0:0'): static
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $day);
    }

    /**
     * 每月执行两次
     *
     * @return $this
     */
    public function twiceMonthly(int $first = 1, int $second = 16): static
    {
        $days = $first . ',' . $second;

        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, $days);
    }

    /**
     * 按季度执行
     *
     * @return $this
     */
    public function quarterly(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, '*/3');
    }

    /**
     * 按年执行
     *
     * @return $this
     */
    public function yearly(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, 1);
    }

    /**
     * 每分钟执行
     *
     * @return $this
     */
    public function everyMinute(): static
    {
        return $this->spliceIntoPosition(1, '*');
    }

    /**
     * 每5分钟执行
     *
     * @return $this
     */
    public function everyFiveMinutes(): static
    {
        return $this->spliceIntoPosition(1, '*/5');
    }

    /**
     * 每10分钟执行
     *
     * @return $this
     */
    public function everyTenMinutes(): static
    {
        return $this->spliceIntoPosition(1, '*/10');
    }

    /**
     * 每30分钟执行
     *
     * @return $this
     */
    public function everyThirtyMinutes(): static
    {
        return $this->spliceIntoPosition(1, '0,30');
    }

    /**
     * 按周设置天执行
     *
     * @param array<int, string|int>|string|int $days
     * @param string|int ...$more
     * @return $this
     */
    public function days(array|string|int $days, array|string|int ...$more): static
    {
        $days = is_array($days) ? $days : func_get_args();

        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * 设置时区
     *
     * @return $this
     */
    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @param int|string $value
     * @return $this
     */
    protected function spliceIntoPosition(int $position, int|string $value): static
    {
        $segments = explode(' ', $this->expression);

        // cron 表达式只有 5 个字段，position 必须在 1-5 之间
        if ($position < 1 || $position > 5) {
            return $this;
        }

        // 填充至少 5 个字段，避免表达式格式异常时产生 Undefined offset Notice
        for ($i = count($segments); $i < 5; $i++) {
            $segments[$i] = '*';
        }

        $segments[$position - 1] = (string) $value;

        return $this->expression(implode(' ', $segments));
    }
}