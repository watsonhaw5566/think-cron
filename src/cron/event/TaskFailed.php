<?php

namespace watsonhaw\cron\event;

use Throwable;
use watsonhaw\cron\Task;

class TaskFailed extends TaskEvent
{
    public Throwable $exception;

    public function __construct(Task $task, Throwable $exception)
    {
        parent::__construct($task);
        $this->exception = $exception;
    }
}