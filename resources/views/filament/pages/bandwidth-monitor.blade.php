@php
    $sync = $this->getSyncStatus();
    $api = $sync['api'] ?? [];
    $radius = $sync['radius'] ?? [];
    $updated = $sync['updated_at'] ?? null;
    $chartPoll = (int) config('bandwidth.monitor_wan_poll_seconds', 10);
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 to-white p-5 shadow-sm dark:border-sky-900/50 dark:from-sky-950/40 dark:to-gray-900">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-300">MikroTik API</p>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' => ! empty($api['ok']),
                        'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200' => empty($api['ok']),
                    ])>{{ ! empty($api['ok']) ? 'Live' : 'Check' }}</span>
                </div>
                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($api['sessions'] ?? 0)) }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Active PPP sessions from RouterOS API</p>
                @if (! empty($api['error']))
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ $api['error'] }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm dark:border-violet-900/50 dark:from-violet-950/40 dark:to-gray-900">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">FreeRADIUS</p>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' => ! empty($radius['ok']),
                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => empty($radius['ok']),
                    ])>{{ ! empty($radius['ok']) ? 'Live' : (config('radius.accounting_enabled') ? 'Error' : 'Off') }}</span>
                </div>
                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($radius['sessions'] ?? $radius['active_sessions'] ?? 0)) }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Open radacct rows (interim accounting)</p>
                @if (! config('radius.accounting_enabled'))
                    <p class="mt-2 text-xs text-gray-500">Set <span class="font-mono">RADIUS_ACCOUNTING_ENABLED=true</span> + DB in .env</p>
                @elseif (! empty($radius['message']) && empty($radius['ok']))
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ $radius['message'] }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm dark:border-emerald-900/50 dark:from-emerald-950/40 dark:to-gray-900">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">Merged online</p>
                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($sync['merged_active'] ?? 0)) }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Billing panel (API ∪ RADIUS, no duplicate users)</p>
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

        <div class="rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <nav class="flex flex-wrap gap-1" role="tablist">
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
                        @class([
                            'rounded-lg px-4 py-2 text-sm font-medium transition',
                            'bg-primary-500 text-white shadow' => $activeTab === $key,
                            'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $activeTab !== $key,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

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
