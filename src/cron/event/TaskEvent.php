<?php

namespace watsonhaw\cron\event;

use watsonhaw\cron\Task;

abstract class TaskEvent
{
    public Task $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function getName(): string
    {
        return get_class($this->task);
    }
}