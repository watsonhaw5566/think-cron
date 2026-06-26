<?php

namespace watsonhaw\cron;

use Carbon\Carbon;

trait ManagesFrequencies
{
    /**
     * 设置任务执行周期
     *
     * @param string $expression
     * @return $this
     */
    public function expression($expression)
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * 设置区间时间
     *
     * @param string $startTime
     * @param string $endTime
     * @return $this
     */
    public function between($startTime, $endTime)
    {
        return $this->when($this->inTimeInterval($startTime, $endTime));
    }

    /**
     * 排除区间时间
     *
     * @param string $startTime
     * @param string $endTime
     * @return $this
     */
    public function unlessBetween($startTime, $endTime)
    {
        return $this->skip($this->inTimeInterval($startTime, $endTime));
    }

    private function inTimeInterval($startTime, $endTime)
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
    public function hourly()
    {
        return $this->spliceIntoPosition(1, 0);
    }

    /**
     * 按小时延期执行
     *
     * @param int $offset
     * @return $this
     */
    public function hourlyAt($offset)
    {
        return $this->spliceIntoPosition(1, $offset);
    }

    /**
     * 按天执行
     *
     * @return $this
     */
    public function daily()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0);
    }

    /**
     * 指定时间执行
     *
     * @param string $time
     * @return $this
     */
    public function at($time)
    {
        return $this->dailyAt($time);
    }

    /**
     * 指定时间执行
     *
     * @param string $time
     * @return $this
     */
    public function dailyAt($time)
    {
        $segments = explode(':', $time);

        return $this->spliceIntoPosition(2, (int) $segments[0])
            ->spliceIntoPosition(1, count($segments) == 2 ? (int) $segments[1] : '0');
    }

    /**
     * 每天执行两次
     *
     * @param int $first
     * @param int $second
     * @return $this
     */
    public function twiceDaily($first = 1, $second = 13)
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
    public function weekdays()
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    /**
     * 周末执行
     *
     * @return $this
     */
    public function weekends()
    {
        return $this->spliceIntoPosition(5, '0,6');
    }

    /**
     * 星期一执行
     *
     * @return $this
     */
    public function mondays()
    {
        return $this->days(1);
    }

    /**
     * 星期二执行
     *
     * @return $this
     */
    public function tuesdays()
    {
        return $this->days(2);
    }

    /**
     * 星期三执行
     *
     * @return $this
     */
    public function wednesdays()
    {
        return $this->days(3);
    }

    /**
     * 星期四执行
     *
     * @return $this
     */
    public function thursdays()
    {
        return $this->days(4);
    }

    /**
     * 星期五执行
     *
     * @return $this
     */
    public function fridays()
    {
        return $this->days(5);
    }

    /**
     * 星期六执行
     *
     * @return $this
     */
    public function saturdays()
    {
        return $this->days(6);
    }

    /**
     * 星期天执行
     *
     * @return $this
     */
    public function sundays()
    {
        return $this->days(0);
    }

    /**
     * 按周执行
     *
     * @return $this
     */
    public function weekly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(5, 0);
    }

    /**
     * 指定每周的时间执行
     *
     * @param int $day
     * @param string $time
     * @return $this
     */
    public function weeklyOn($day, $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(5, $day);
    }

    /**
     * 按月执行
     *
     * @return $this
     */
    public function monthly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1);
    }

    /**
     * 指定每月的执行时间
     *
     * @param int $day
     * @param string $time
     * @return $this
     */
    public function monthlyOn($day = 1, $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $day);
    }

    /**
     * 每月执行两次
     *
     * @param int $first
     * @param int $second
     * @return $this
     */
    public function twiceMonthly($first = 1, $second = 16)
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
    public function quarterly()
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
    public function yearly()
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
    public function everyMinute()
    {
        return $this->spliceIntoPosition(1, '*');
    }

    /**
     * 每5分钟执行
     *
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->spliceIntoPosition(1, '*/5');
    }

    /**
     * 每10分钟执行
     *
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->spliceIntoPosition(1, '*/10');
    }

    /**
     * 每30分钟执行
     *
     * @return $this
     */
    public function everyThirtyMinutes()
    {
        return $this->spliceIntoPosition(1, '0,30');
    }

    /**
     * 按周设置天执行
     *
     * @param array|mixed $days
     * @return $this
     */
    public function days($days)
    {
        $days = is_array($days) ? $days : func_get_args();

        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * 设置时区
     *
     * @param string $timezone
     * @return $this
     */
    public function timezone($timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    protected function spliceIntoPosition($position, $value)
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

        $segments[$position - 1] = $value;

        return $this->expression(implode(' ', $segments));
    }
}