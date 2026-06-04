<?php

namespace Tests\Unit;

use App\Support\Automation\SchedulerRunnerLock;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SchedulerRunnerLockTest extends TestCase
{
    public function test_second_acquire_fails_while_lock_held(): void
    {
        Cache::flush();

        $first = SchedulerRunnerLock::acquire(60);
        $this->assertNotNull($first);

        $second = SchedulerRunnerLock::acquire(60);
        $this->assertNull($second);

        $first->release();
    }
}
