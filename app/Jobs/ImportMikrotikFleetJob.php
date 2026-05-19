<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class ImportMikrotikFleetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /** @param  array<string, mixed>  $options */
    public function __construct(
        public array $options = [],
    ) {}

    public function handle(): void
    {
        Artisan::call('isp:import-mikrotik-secrets', array_merge($this->options, ['--queued' => true]));
    }
}
