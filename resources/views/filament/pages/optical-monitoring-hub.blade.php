@php
    $stats = $this->getOpticalStatsSafe();
    $unlinkedCount = \App\Models\Device::withoutGlobalScopes()
        ->where('type', 'onu')
        ->whereNull('customer_id')
        ->count();
    $linkedCount = \App\Models\Device::withoutGlobalScopes()
        ->where('type', 'onu')
        ->whereNotNull('customer_id')
        ->count();
@endphp

<x-filament-panels::page>
    <div class="space-y-5" wire:poll.60s="$refresh">
        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-6 shadow-sm dark:border-indigo-900/40 dark:from-indigo-950/40 dark:to-gray-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">ONU Optical NOC — dBm power monitor</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Real-time RX/TX dBm · signal health · fiber cut detection · PON port stats.
                <span class="text-emerald-600 font-medium">Green ≥ −15 dBm</span> ·
                <span class="text-amber-600 font-medium">Yellow (weak)</span> ·
                <span class="text-rose-600 font-medium">Red critical (&lt; −27 dBm)</span>
            </p>
            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                    ✓ {{ $linkedCount }} ONU linked to subscribers
                </span>
                @if($unlinkedCount > 0)
                <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                    ⚠ {{ $unlinkedCount }} unlinked — use "Link subscriber" button below to assign
                </span>
                @endif
            </div>
            <p class="mt-2 text-xs text-gray-500">Auto-refresh every 60s · Webhook: POST /api/webhooks/onu-optical-ingest</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/50 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-emerald-700">Avg RX</p>
                <p class="mt-1 text-2xl font-bold">{{ $stats['avg_rx_dbm'] !== null ? $stats['avg_rx_dbm'].' dBm' : '—' }}</p>
            </div>
            <div class="rounded-xl border border-teal-200 bg-white p-4 dark:border-teal-900/50 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-teal-700">Health avg</p>
                <p class="mt-1 text-2xl font-bold">{{ $stats['avg_health'] }}%</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-white p-4 dark:border-blue-900/50 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-blue-700">ONUs</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($stats['total_onus']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-white p-4 dark:border-amber-900/50 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-amber-700">Weak</p>
                <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($stats['warning_onus']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-white p-4 dark:border-rose-900/50 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-rose-700">Critical</p>
                <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($stats['critical_onus']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-300 bg-white p-4 dark:border-gray-600 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-600">Offline</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($stats['offline_onus']) }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 dark:border-rose-900 dark:bg-rose-950/30">
                <p class="font-semibold text-rose-800 dark:text-rose-200">Open alerts</p>
                <p class="text-3xl font-bold text-rose-700">{{ number_format($stats['open_alerts']) }}</p>
            </div>
            <div class="rounded-xl border border-orange-300 bg-orange-50 p-4 dark:border-orange-900 dark:bg-orange-950/30">
                <p class="font-semibold text-orange-800 dark:text-orange-200">Fiber / PON faults</p>
                <p class="text-3xl font-bold text-orange-700">{{ number_format($stats['fiber_faults']) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="setMonitorTab('onus')"
                @class(['rounded-lg px-4 py-2 text-sm font-medium', 'bg-primary-500 text-white' => $monitorTab === 'onus', 'bg-gray-100 dark:bg-gray-800' => $monitorTab !== 'onus'])>
                All ONUs (live dBm)
            </button>
            <button type="button" wire:click="setMonitorTab('alerts')"
                @class(['rounded-lg px-4 py-2 text-sm font-medium', 'bg-primary-500 text-white' => $monitorTab === 'alerts', 'bg-gray-100 dark:bg-gray-800' => $monitorTab !== 'alerts'])>
                Open alerts
            </button>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            {{ $this->table }}
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900/50">
            <p class="font-semibold text-gray-900 dark:text-white">OLT vendors &amp; data sources</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                <li>Webhook example:
                    <code class="text-xs">@verbatim
{"olt_id":2,"create_missing":true,"readings":[{"serial":"SN123","rx_dbm":-15,"tx_dbm":2}]}
@endverbatim</code></li>
                <li>Match by: serial · onu_id · ppp_login · customer_code · phone — or auto-create ONU</li>
                <li>SNMP OLT poll: isp:poll-olt-intelligence every 10 min</li>
                <li>Signal history: isp:collect-onu-signals · hourly rollups in onu_signal_logs</li>
            </ul>
        </div>
    </div>
</x-filament-panels::page>
