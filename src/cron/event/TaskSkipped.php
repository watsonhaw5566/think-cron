<?php

namespace watsonhaw\cron\event;

use watsonhaw\cron\Task;

class TaskSkipped extends TaskEvent
{
    /**
     * 跳过原因：
     *   'single_server' - 其他服务器已运行 (onOneServer 机制)
     *   'overlapping'   - 上一次执行尚未结束 (withoutOverlapping 机制)
     */
    public string $reason;

    public function __construct(Task $task, string $reason = 'single_server')
    {
        parent::__construct($task);
        $this->reason = $reason;
    }
}