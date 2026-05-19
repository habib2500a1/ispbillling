<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class RunDunningRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public bool $dryRun = false,
    ) {}

    public function handle(): void
    {
        $params = ['--queued' => true];
        if ($this->dryRun) {
            $params['--dry-run'] = true;
        }
        Artisan::call('isp:send-invoice-due-reminders', $params);
    }
}
