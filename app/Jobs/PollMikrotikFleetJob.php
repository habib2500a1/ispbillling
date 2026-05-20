<?php

namespace App\Jobs;

use App\Services\Mikrotik\MikrotikFleetCoordinator;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollMikrotikFleetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public ?int $tenantId = null,
    ) {}

    public function handle(MikrotikFleetCoordinator $fleet): void
    {
        if (! config('mikrotik.poll_enabled', true)) {
            return;
        }

        $stats = $fleet->probeAllServers($this->tenantId);

        Log::info('mikrotik.fleet.poll_complete', [
            'tenant_id' => $this->tenantId,
            'polled' => $stats['polled'],
            'online' => $stats['online'],
            'offline' => $stats['offline'],
        ]);

        if (($stats['offline'] ?? 0) > 0 && config('alerts.mikrotik_offline_enabled', true)) {
            $offlineNames = collect($stats['servers'] ?? [])
                ->where('status', '!=', 'online')
                ->pluck('name')
                ->implode(', ');

            app(NotificationDispatcher::class)->notifyOps(
                (int) ($this->tenantId ?? 1),
                NotificationEvent::OUTAGE,
                [
                    'message' => 'MikroTik offline: '.$offlineNames,
                    'count' => $stats['offline'],
                    'customer_list' => $offlineNames !== '' ? 'Servers: '.$offlineNames : '—',
                ],
            );
        }
    }
}
