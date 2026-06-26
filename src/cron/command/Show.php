<?php

namespace watsonhaw\cron\command;

use Carbon\Carbon;
use Cron\CronExpression;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\Table;
use watsonhaw\cron\Scheduler;
use watsonhaw\cron\Task;

class Show extends Command
{
    protected function configure(): void
    {
        $this->setName('cron:show');
    }

    protected function execute(Input $input, Output $output): int
    {
        $scheduler = $this->app->make(Scheduler::class);
        $tasks     = $scheduler->getTasks();

        if (count($tasks) === 0) {
            $output->writeln('<info>No scheduled tasks registered.</info>');
            return 0;
        }

        $table = new Table();
        $table->setHeader(['Task', 'Expression', 'Next Run', 'Timezone', 'Without Overlap', 'On One Server']);

        $now = Carbon::now();
        $rows = [];

        foreach ($tasks as $task) {
            /** @var Task $task */
            $expression = $task->expression;

            try {
                $cron       = new CronExpression($expression);
                $nextRun    = $cron->getNextRunDate($now->toDateTimeLocalString(), 0, false, $task->timezone);
                $nextRunStr = Carbon::instance($nextRun)->toDateTimeString();
            } catch (\Throwable $e) {
                $nextRunStr = '<error>Invalid expression</error>';
            }

            $rows[] = [
                get_class($task),
                $expression,
                $nextRunStr,
                $task->timezone ?? '(default)',
                $task->withoutOverlapping ? 'Yes' : 'No',
                $task->onOneServer ? 'Yes' : 'No',
            ];
        }

        $table->setRows($rows);
        $this->table($table);

        $output->writeln(count($tasks) . ' task(s) total.');

        return 0;
    }
}