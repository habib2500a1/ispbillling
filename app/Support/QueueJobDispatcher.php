<?php

namespace App\Support;

use Illuminate\Contracts\Queue\ShouldQueue;

final class QueueJobDispatcher
{
    /**
     * @param  callable(): void  $sync
     */
    public static function run(ShouldQueue $job, callable $sync): void
    {
        if (config('queue_ops.heavy_jobs_enabled', false)) {
            dispatch($job);

            return;
        }

        $sync();
    }
}
