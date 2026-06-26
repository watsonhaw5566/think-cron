<?php

namespace watsonhaw\cron\tests;

use PHPUnit\Framework\TestCase;
use watsonhaw\cron\ManagesFrequencies;

final class ManagesFrequenciesTest extends TestCase
{
    use ManagesFrequencies;

    public string $expression = '* * * * *';
    public ?string $timezone = null;
    private array $filters = [];
    private array $rejects = [];

    protected function setUp(): void
    {
        $this->expression = '* * * * *';
        $this->timezone   = null;
        $this->filters    = [];
        $this->rejects    = [];
    }

    // Task 中定义的 when/skip，在这里提供最小实现，让 between/unlessBetween 能工作
    public function when(callable $callback): self
    {
        $this->filters[] = $callback;
        return $this;
    }

    public function skip(callable $callback): self
    {
        $this->rejects[] = $callback;
        return $this;
    }

    public function test_it_can_set_expression_directly(): void
    {
        $result = $this->expression('0 12 * * *');

        self::assertSame($this, $result);
        self::assertSame('0 12 * * *', $this->expression);
    }

    public function test_it_schedules_hourly(): void
    {
        $this->hourly();

        self::assertSame('0 * * * *', $this->expression);
    }

    public function test_it_schedules_hourly_at_offset(): void
    {
        $this->hourlyAt(15);

        self::assertSame('15 * * * *', $this->expression);
    }

    public function test_it_schedules_daily(): void
    {
        $this->daily();

        self::assertSame('0 0 * * *', $this->expression);
    }

    public function test_it_schedules_daily_at_time(): void
    {
        $this->dailyAt('13:45');

        self::assertSame('45 13 * * *', $this->expression);
    }

    public function test_it_schedules_at_alias_to_daily_at(): void
    {
        $this->at('9:30');

        self::assertSame('30 9 * * *', $this->expression);
    }

    public function test_it_schedules_daily_at_without_minute_segment(): void
    {
        $this->dailyAt('8');

        self::assertSame('0 8 * * *', $this->expression);
    }

    public function test_it_schedules_twice_daily(): void
    {
        $this->twiceDaily(1, 13);

        self::assertSame('0 1,13 * * *', $this->expression);
    }

    public function test_it_schedules_weekdays(): void
    {
        $this->weekdays();

        self::assertSame('* * * * 1-5', $this->expression);
    }

    public function test_it_schedules_weekends(): void
    {
        $this->weekends();

        self::assertSame('* * * * 0,6', $this->expression);
    }

    public function test_it_schedules_specific_weekday_methods(): void
    {
        $this->mondays();
        self::assertSame('* * * * 1', $this->expression);

        $this->expression = '* * * * *';
        $this->sundays();
        self::assertSame('* * * * 0', $this->expression);
    }

    public function test_it_schedules_weekly(): void
    {
        $this->weekly();

        self::assertSame('0 0 * * 0', $this->expression);
    }

    public function test_it_schedules_weekly_on_specific_day_and_time(): void
    {
        $this->weeklyOn(3, '10:30');

        self::assertSame('30 10 * * 3', $this->expression);
    }

    public function test_it_schedules_monthly(): void
    {
        $this->monthly();

        self::assertSame('0 0 1 * *', $this->expression);
    }

    public function test_it_schedules_monthly_on_specific_day_and_time(): void
    {
        $this->monthlyOn(15, '6:20');

        self::assertSame('20 6 15 * *', $this->expression);
    }

    public function test_it_schedules_twice_monthly(): void
    {
        $this->twiceMonthly(1, 16);

        self::assertSame('0 0 1,16 * *', $this->expression);
    }

    public function test_it_schedules_quarterly(): void
    {
        $this->quarterly();

        self::assertSame('0 0 1 */3 *', $this->expression);
    }

    public function test_it_schedules_yearly(): void
    {
        $this->yearly();

        self::assertSame('0 0 1 1 *', $this->expression);
    }

    public function test_it_schedules_every_minute_families(): void
    {
        $this->everyMinute();
        self::assertSame('* * * * *', $this->expression);

        $this->everyFiveMinutes();
        self::assertSame('*/5 * * * *', $this->expression);

        $this->everyTenMinutes();
        self::assertSame('*/10 * * * *', $this->expression);

        $this->everyThirtyMinutes();
        self::assertSame('0,30 * * * *', $this->expression);
    }

    public function test_it_schedules_days_with_array_and_variadic(): void
    {
        $this->days([1, 3, 5]);
        self::assertSame('* * * * 1,3,5', $this->expression);

        $this->expression = '* * * * *';
        $this->days(0, 6);
        self::assertSame('* * * * 0,6', $this->expression);
    }

    public function test_it_can_set_timezone(): void
    {
        $result = $this->timezone('Asia/Shanghai');

        self::assertSame($this, $result);
        self::assertSame('Asia/Shanghai', $this->timezone);
    }

    public function test_between_adds_filter_and_unless_between_adds_reject(): void
    {
        $beforeFilters = $this->filters ?? [];
        $beforeRejects = $this->rejects ?? [];

        $this->between('00:00', '23:59');
        self::assertCount(count($beforeFilters) + 1, $this->filters);

        $this->unlessBetween('00:00', '00:01');
        self::assertCount(count($beforeRejects) + 1, $this->rejects);
    }
}