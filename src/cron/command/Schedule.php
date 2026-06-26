<?php

namespace yunwuxin\cron\command;

use Symfony\Component\Process\Process;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Schedule extends Command
{

    protected function configure()
    {
        $this->setName('cron:schedule');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('<info>Cron schedule started.</info> Press Ctrl+C to stop.');

        $command = '"' . PHP_BINARY . '" think cron:run';

        $shouldStop = false;

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use (&$shouldStop) {
                $shouldStop = true;
            });
            pcntl_signal(SIGTERM, function () use (&$shouldStop) {
                $shouldStop = true;
            });
        }

        while (!$shouldStop) {
            $process = Process::fromShellCommandline($command);
            $exitCode = $process->run();

            if ($exitCode !== 0) {
                $output->writeln(
                    '<error>cron:run exited with code ' . $exitCode . '</error>'
                );
            }

            for ($i = 0; $i < 60; $i++) {
                if ($shouldStop) {
                    break;
                }
                sleep(1);
            }
        }

        $output->writeln('<info>Cron schedule stopped.</info>');

        return 0;
    }
}