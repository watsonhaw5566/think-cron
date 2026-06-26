<?php

namespace watsonhaw\cron\tests;

use Exception;
use PHPUnit\Framework\TestCase;
use watsonhaw\cron\event\TaskEvent;
use watsonhaw\cron\event\TaskFailed;
use watsonhaw\cron\event\TaskProcessed;
use watsonhaw\cron\event\TaskSkipped;
use watsonhaw\cron\Task;

final class TaskEventTest extends TestCase
{
    public function test_it_exposes_task_and_name(): void
    {
        $task  = $this->createStub(Task::class);
        $event = new class($task) extends TaskEvent {
        };

        self::assertSame($task, $event->task);
        self::assertSame(get_class($task), $event->getName());
    }

    public function test_task_failed_carries_exception(): void
    {
        $task      = $this->createStub(Task::class);
        $exception = new Exception('boom');
        $event     = new TaskFailed($task, $exception);

        self::assertSame($task, $event->task);
        self::assertSame($exception, $event->exception);
        self::assertSame(get_class($task), $event->getName());
    }

    public function test_task_processed_is_a_plain_event(): void
    {
        $task  = $this->createStub(Task::class);
        $event = new TaskProcessed($task);

        self::assertSame($task, $event->task);
    }

    public function test_task_skipped_is_a_plain_event(): void
    {
        $task  = $this->createStub(Task::class);
        $event = new TaskSkipped($task);

        self::assertSame($task, $event->task);
    }
}