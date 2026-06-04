<?php

namespace App\Support\Automation;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Prevents overlapping isp:run-automatic-processes workers (PHP-FPM exhaustion / 502).
 */
final class SchedulerRunnerLock
{
    public const LOCK_KEY = 'isp:automatic-processes-runner';

    public static function acquire(?int $seconds = null): ?Lock
    {
        $seconds = max(60, $seconds ?? (int) config('automation.runner_lock_seconds', 300));

        $lock = Cache::lock(self::LOCK_KEY, $seconds);

        return $lock->get() ? $lock : null;
    }
}
