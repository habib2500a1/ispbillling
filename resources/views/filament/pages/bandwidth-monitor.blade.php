@php
    $sync = $this->getSyncStatus();
    $api = $sync['api'] ?? [];
    $radius = $sync['radius'] ?? [];
    $updated = $sync['updated_at'] ?? null;
    $wanCollect = max(3, (int) config('bandwidth.monitor_wan_collect_seconds', 3));
    $syncRunning = \App\Services\Bandwidth\BandwidthSyncStatus::isRunning(
        \App\Support\TenantResolver::requiredTenantId()
    );
@endphp

{!! \App\Support\NetworkStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-bandwidth-monitor-page">
    <div
        class="net-noc-pro space-y-4"
        @if ($activeTab === 'graphs' && $wanCollect > 0)
            wire:poll.{{ $wanCollect }}s="refreshLiveData"
        @endif
    >
        <header class="mon-hero">
            <h1 class="mon-hero__title">Bandwidth monitor</h1>
            <p class="mon-hero__sub">Dual sync from MikroTik API and FreeRADIUS — live sessions, WAN graphs, usage, and abuse alerts.</p>
        </header>

        <div class="bw-sync-grid">
            <div class="bw-sync-card bw-sync-card--api">
                <div class="flex items-center justify-between">
                    <p class="bw-sync-card__label">MikroTik API</p>
                    <span @class([
                        'bw-sync-badge',
                        'bw-sync-badge--ok' => ! empty($api['ok']),
                        'bw-sync-badge--err' => empty($api['ok']),
                    ])>{{ ! empty($api['ok']) ? 'Live' : 'Check' }}</span>
                </div>
                <p class="bw-sync-card__value">{{ number_format((int) ($api['sessions'] ?? 0)) }}</p>
                <p class="mt-1 text-sm" style="color:var(--nr-muted);">Active PPP sessions from RouterOS API</p>
                @if (! empty($api['error']))
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ $api['error'] }}</p>
                @endif
            </div>

            <div class="bw-sync-card bw-sync-card--radius">
                <div class="flex items-center justify-between">
                    <p class="bw-sync-card__label">FreeRADIUS</p>
                    <span @class([
                        'bw-sync-badge',
                        'bw-sync-badge--ok' => ! empty($radius['ok']),
                        'bw-sync-badge--err' => empty($radius['ok']) && config('radius.accounting_enabled'),
                    ])>{{ ! empty($radius['ok']) ? 'Live' : (config('radius.accounting_enabled') ? 'Error' : 'Off') }}</span>
                </div>
                <p class="bw-sync-card__value">{{ number_format((int) ($radius['sessions'] ?? $radius['active_sessions'] ?? 0)) }}</p>
                <p class="mt-1 text-sm" style="color:var(--nr-muted);">Open radacct rows (interim accounting)</p>
                @if (! config('radius.accounting_enabled'))
                    <p class="mt-2 text-xs text-gray-500">Set <span class="font-mono">RADIUS_ACCOUNTING_ENABLED=true</span> + DB in .env</p>
                @elseif (! empty($radius['message']) && empty($radius['ok']))
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ $radius['message'] }}</p>
                @endif
            </div>

            <div class="bw-sync-card bw-sync-card--merged">
                <p class="bw-sync-card__label">Merged online</p>
                <p class="bw-sync-card__value">{{ number_format((int) ($sync['merged_active'] ?? 0)) }}</p>
                <p class="mt-1 text-sm" style="color:var(--nr-muted);">Billing panel (API ∪ RADIUS, no duplicate users)</p>
                @if (! empty($sync['matched_subscribers']))
                    <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">{{ number_format((int) $sync['matched_subscribers']) }} matched to subscribers</p>
                @endif
                @if (! empty($sync['unmatched_logins']) && is_array($sync['unmatched_logins']))
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Unmatched: {{ implode(', ', array_slice($sync['unmatched_logins'], 0, 4)) }}{{ count($sync['unmatched_logins']) > 4 ? '…' : '' }}</p>
                @endif
                @if ($updated)
                    <p class="mt-2 text-xs text-gray-500">Last sync: {{ rescue(fn () => \Carbon\Carbon::parse($updated)->diffForHumans(), $updated) }}</p>
                @endif
            </div>
        </div>

        <nav class="bw-tabs" role="tablist">
            @foreach ([
                'online' => 'Online users',
                'graphs' => 'WAN graphs',
                'history' => 'Session history',
                'usage' => 'Daily / monthly usage',
                'abuse' => 'Abuse alerts',
            ] as $key => $label)
                <button
                    type="button"
                    wire:click="setActiveTab('{{ $key }}')"
                    wire:loading.attr="disabled"
                    @class(['bw-tab', 'bw-tab--active' => $activeTab === $key])
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        @if ($syncRunning)
            <p class="mt-2 text-sm text-sky-700 dark:text-sky-300">
                Background sync running on server — charts refresh automatically without Cloudflare timeout.
            </p>
        @endif

        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
            <p class="font-semibold text-gray-900 dark:text-white">Dual sync (API + RADIUS)</p>
            <p class="mt-2">
                <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                    Open Online clients monitoring →
                </a>
                (total / online / offline like legacy panel)
            </p>
            <p class="mt-1">
                <strong>Search:</strong> table-এ ID, name, phone, PPP user লিখুন — অথবা top bar-এ <strong>Ctrl+K</strong> (global search)।
                WAN graphs tab-এ chart দেখুন। Sync: <strong>Sync now</strong> বা cron <span class="font-mono text-xs">isp:collect-bandwidth</span>.
            </p>
            @if (! config('bandwidth.collection_enabled', true))
                <p class="mt-2 text-amber-700 dark:text-amber-300">
                    Bandwidth collection disabled — set <span class="font-mono">BANDWIDTH_COLLECTION_ENABLED=true</span>.
                </p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
