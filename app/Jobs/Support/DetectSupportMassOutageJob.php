<?php

namespace App\Jobs\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class DetectSupportMassOutageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public ?int $tenantId = null,
    ) {}

    public function handle(): void
    {
        $options = $this->tenantId !== null ? ['--tenant' => $this->tenantId] : [];
        Artisan::call('isp:support-detect-mass-outage', $options);
    }
}
