<?php

namespace watsonhaw\cron\command;

use Carbon\Carbon;
use think\console\Command;
use think\exception\Handle;
use watsonhaw\cron\event\TaskFailed;
use watsonhaw\cron\event\TaskProcessed;
use watsonhaw\cron\event\TaskSkipped;
use watsonhaw\cron\Scheduler;

class Run extends Command
{
    protected function configure()
    {
        $this->setName('cron:run');
    }

    public function handle(Scheduler $scheduler)
    {
        $this->listenForEvents();

        $scheduler->run();
    }

    /**
     * 注册事件
     */
    protected function listenForEvents()
    {
        $this->app->event->listen(TaskProcessed::class, function (TaskProcessed $event) {
            $this->output->writeln("Task {$event->getName()} run at " . Carbon::now());
        });

        $this->app->event->listen(TaskSkipped::class, function (TaskSkipped $event) {
            $reason = $event->reason === 'overlapping'
                ? 'previous run not finished'
                : 'has already run on another server';
            $this->output->writeln("<info>Skipping task ({$reason}):</info> " . $event->getName());
        });

        $this->app->event->listen(TaskFailed::class, function (TaskFailed $event) {
            $this->output->writeln("Task {$event->getName()} failed at " . Carbon::now());

            /** @var Handle $handle */
            $handle = $this->app->make(Handle::class);

            $handle->renderForConsole($this->output, $event->exception);

            $handle->report($event->exception);
        });
    }

}