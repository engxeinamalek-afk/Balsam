<?php

namespace App\Traits\Console\Concerns;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

trait PreventsConcurrentExecution
{
    protected ?Lock $concurrentExecutionLock = null;

    protected function runWithCommandLock(callable $callback, int $lockSeconds = 1800): int
    {
        $commandName = (string) $this->getName();
        $this->concurrentExecutionLock = Cache::lock(
            "console-command:{$commandName}",
            $lockSeconds
        );

        if (! $this->concurrentExecutionLock->get()) {
            $this->warn("Command [{$commandName}] is already running. Skipping this duplicate execution.");

            return self::FAILURE;
        }

        try {
            return $callback();
        } finally {
            $this->concurrentExecutionLock->release();
            $this->concurrentExecutionLock = null;
        }
    }
}
