<?php

namespace yunwuxin\cron;

use yunwuxin\cron\command\Run;
use yunwuxin\cron\command\Schedule;

class Service extends \think\Service
{

    public function boot()
    {
        $this->commands([
            Run::class,
            Schedule::class,
        ]);

        // 仅在安装了 think-swoole 的环境注册，避免非 Swoole 项目因顶层
        // use 导入不存在的类而在文件加载阶段触发致命错误
        if (class_exists('\\think\\swoole\\Manager') && class_exists('\\Swoole\\Timer')) {
            $this->app->event->listen('swoole.init', function (\think\swoole\Manager $manager) {
                $manager->addWorker(function () use ($manager) {
                    \Swoole\Timer::tick(60 * 1000, function () use ($manager) {
                        $manager->runWithBarrier([$manager, 'runInSandbox'], function (Scheduler $scheduler) {
                            $scheduler->run();
                        });
                    });
                }, "cron");
            });
        }
    }
}