<?php

namespace yunwuxin\cron\event;

use Throwable;
use yunwuxin\cron\Task;

class TaskFailed extends TaskEvent
{
    public Throwable $exception;

    public function __construct(Task $task, Throwable $exception)
    {
        parent::__construct($task);
        $this->exception = $exception;
    }
}