<?php

namespace yunwuxin\cron\tests;

use Exception;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Cache;
use yunwuxin\cron\Task;

final class TaskTest extends TestCase
{
    private function makeApp(): App
    {
        $app = new App();
        $app->instance('config', new class {
            public function get(string $key, mixed $default = null): mixed
            {
                return $default;
            }
        });

        return $app;
    }

    public function test_it_reports_as_due_when_expression_matches(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            public $expression = '* * * * *';

            protected function configure(): void
            {
            }

            protected function execute(): void
            {
            }
        };

        self::assertTrue($task->isDue());
    }

    public function test_it_reports_not_due_for_far_future_expression(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            public $expression = '0 0 1 1 *';

            protected function configure(): void
            {
            }

            protected function execute(): void
            {
            }
        };

        self::assertFalse($task->isDue());
    }

    public function test_it_passes_filters_when_no_callbacks_registered(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            protected function configure(): void
            {
            }

            protected function execute(): void
            {
            }
        };

        self::assertTrue($task->filtersPass());
    }

    public function test_it_passes_filters_when_when_callback_returns_true(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            protected function configure(): void
            {
                $this->when(static fn () => true);
            }

            protected function execute(): void
            {
            }
        };

        self::assertTrue($task->filtersPass());
    }

    public function test_it_fails_filters_when_when_callback_returns_false(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            protected function configure(): void
            {
                $this->when(static fn () => false);
            }

            protected function execute(): void
            {
            }
        };

        self::assertFalse($task->filtersPass());
    }

    public function test_it_fails_filters_when_skip_callback_returns_true(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            protected function configure(): void
            {
                $this->skip(static fn () => true);
            }

            protected function execute(): void
            {
            }
        };

        self::assertFalse($task->filtersPass());
    }

    public function test_it_passes_filters_when_skip_callback_returns_false(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            protected function configure(): void
            {
                $this->skip(static fn () => false);
            }

            protected function execute(): void
            {
            }
        };

        self::assertTrue($task->filtersPass());
    }

    public function test_without_overlapping_sets_flag_and_ttl(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            protected function configure(): void
            {
            }

            protected function execute(): void
            {
            }
        };

        $result = $task->withoutOverlapping(60);

        self::assertSame($task, $result);
        self::assertTrue($task->withoutOverlapping);
        self::assertSame(60, $task->expiresAt);
    }

    public function test_on_one_server_toggles_flag(): void
    {
        $task = new class($this->makeApp(), new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return true; }
            public function delete($key): bool { return true; }
        }) extends Task {
            protected function configure(): void
            {
            }

            protected function execute(): void
            {
            }
        };

        $result = $task->onOneServer();

        self::assertSame($task, $result);
        self::assertTrue($task->onOneServer);
    }

    public function test_run_invokes_execute_and_manages_mutex(): void
    {
        $cacheData = [];
        $cache = new class($cacheData) extends Cache {
            public array $data;
            public function __construct(array &$data) { $this->data = &$data; }
            public function has($key): bool { return isset($this->data[$key]); }
            public function get($key, $default = null): mixed { return $this->data[$key] ?? $default; }
            public function set($key, $value, $ttl = null): bool { $this->data[$key] = $value; return true; }
            public function delete($key): bool { unset($this->data[$key]); return true; }
        };

        $task = new class($this->makeApp(), $cache) extends Task {
            public $withoutOverlapping = true;
            public bool $executeCalled = false;

            protected function configure(): void
            {
            }

            protected function execute(): void
            {
                $this->executeCalled = true;
            }
        };

        $task->run();

        self::assertTrue($task->executeCalled);
    }

    public function test_run_skips_when_mutex_cannot_be_created(): void
    {
        $cache = new class extends Cache {
            public function __construct() {}
            public function has($key): bool { return false; }
            public function get($key, $default = null): mixed { return $default; }
            public function set($key, $value, $ttl = null): bool { return false; }
            public function delete($key): bool { return true; }
        };

        $task = new class($this->makeApp(), $cache) extends Task {
            public $withoutOverlapping = true;
            public bool $executeCalled = false;

            protected function configure(): void
            {
            }

            protected function execute(): void
            {
                $this->executeCalled = true;
            }
        };

        $task->run();

        self::assertFalse($task->executeCalled);
    }
}