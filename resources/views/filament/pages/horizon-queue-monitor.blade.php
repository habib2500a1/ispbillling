@php
    $stats = $this->getStats();
    $statCards = [
        [
            'label' => 'Horizon',
            'value' => $stats['horizon_running'] ? 'Running' : 'Stopped',
            'hint' => 'Background worker supervisor',
            'class' => $stats['horizon_running'] ? 'isp-hub-stat--emerald' : 'isp-hub-stat--danger',
            'valueClass' => $stats['horizon_running'] ? '' : 'isp-hub-stat-value--danger',
        ],
        [
            'label' => 'Pending jobs',
            'value' => (string) $stats['pending_jobs'],
            'hint' => 'Waiting in queue',
            'class' => 'isp-hub-stat--sky',
        ],
        [
            'label' => 'Failed jobs',
            'value' => (string) $stats['failed_jobs'],
            'hint' => 'Needs retry or review',
            'class' => $stats['failed_jobs'] > 0 ? 'isp-hub-stat--amber' : 'isp-hub-stat--slate',
        ],
        [
            'label' => 'Queue driver',
            'value' => strtoupper($stats['queue_connection']),
            'hint' => $stats['heavy_jobs_enabled'] ? 'Heavy jobs enabled' : 'Heavy jobs off',
            'class' => 'isp-hub-stat--violet',
        ],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.visible.{{ \App\Support\PerformanceSettings::queueMonitorPollSeconds() }}s>
        <x-isp.hub-hero
            eyebrow="Background jobs"
            title="Queue monitor"
            description="Horizon supervises Redis queues for billing, SMS, MikroTik polling, and other async work. Open the full dashboard for throughput, failed jobs, and worker metrics."
            class="isp-hub-hero--violet"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ $stats['horizon_running'] ? 'Horizon active' : 'Horizon inactive' }}</span>
                    <span class="isp-hub-section__meta">{{ $stats['pending_jobs'] }} pending · {{ $stats['failed_jobs'] }} failed</span>
                </div>
                <x-filament::button
                    tag="a"
                    href="{{ $stats['horizon_url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    icon="heroicon-o-arrow-top-right-on-square"
                >
                    Open Horizon dashboard
                </x-filament::button>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <section class="isp-ops-panel">
            <div class="isp-ops-panel__head">
                <div>
                    <h3 class="isp-ops-panel__title">Horizon dashboard</h3>
                    <p class="isp-ops-panel__desc">
                        Full queue UI at
                        <a href="{{ $stats['horizon_url'] }}" target="_blank" rel="noopener noreferrer" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                            {{ $stats['horizon_url'] }}
                        </a>
                        — available to super-admin and isp-admin while signed in to admin.
                    </p>
                </div>
                <span class="isp-ops-pill {{ $stats['horizon_running'] ? 'isp-ops-pill--ok' : 'isp-ops-pill--danger' }}">
                    {{ $stats['horizon_running'] ? 'Healthy' : 'Check service' }}
                </span>
            </div>

            @unless ($stats['horizon_running'])
                <div class="isp-hub-empty mt-4 text-left">
                    Horizon is not running. On the server, start it with
                    <code class="text-xs">sudo systemctl start laravel-horizon</code>
                    or run <code class="text-xs">./scripts/ensure-redis-horizon.sh</code> after deploy.
                </div>
            @endunless
        </section>
    </div>
</x-filament-panels::page>
