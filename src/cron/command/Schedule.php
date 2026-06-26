<?php

namespace watsonhaw\cron\command;

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

        while (true) {
            $process = Process::fromShellCommandline($command);
            $exitCode = $process->run();

            if ($exitCode !== 0) {
                $output->writeln(
                    '<error>cron:run exited with code ' . $exitCode . '</error>'
                );
            }

            sleep(60);
        }
    }
}