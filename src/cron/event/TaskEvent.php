<?php

namespace yunwuxin\cron\event;

use yunwuxin\cron\Task;

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