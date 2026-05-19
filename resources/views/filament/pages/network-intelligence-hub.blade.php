@php $stats = $this->getStats(); @endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <div class="rounded-2xl border border-cyan-200 bg-gradient-to-br from-cyan-50 via-white to-indigo-50/50 p-6 shadow-sm dark:border-cyan-900/40 dark:from-cyan-950/40 dark:via-gray-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Network intelligence</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                ONU/OLT integration · GPON profiles · SNMP monitoring · NetFlow traffic analysis.
            </p>
            <p class="mt-2 text-xs text-gray-500">
                SNMP: {{ $stats['snmp_available'] ? 'ext-snmp loaded' : 'ext-snmp missing — install php-snmp' }}
                · Last poll: {{ $stats['last_poll'] ?? 'never' }}
                @if($stats['last_poll_ok'] !== null) ({{ $stats['last_poll_ok'] ? 'OK' : 'fail' }}) @endif
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-violet-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-violet-600">OLTs</p>
                <p class="mt-1 text-3xl font-bold">{{ $stats['olts'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-emerald-600">ONUs online</p>
                <p class="mt-1 text-3xl font-bold text-emerald-700">{{ $stats['onus_online'] }} <span class="text-lg text-gray-400">/ {{ $stats['onus'] }}</span></p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-rose-600">ONUs offline</p>
                <p class="mt-1 text-3xl font-bold text-rose-700">{{ $stats['onus_offline'] }}</p>
            </div>
            <div class="rounded-xl border border-sky-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-sky-600">NetFlow (24h)</p>
                <p class="mt-1 text-3xl font-bold">{{ number_format($stats['flows_24h']) }}</p>
            </div>
        </div>

        <x-isp.hub-section-nav group="Network" :hub-url="\App\Filament\Pages\NetworkIntelligenceHub::getUrl()" hub-label="Network center" />

        <x-isp.hub-module-grid group="Network" />

        <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="font-semibold">Automation</h3>
            <ul class="mt-2 list-inside list-disc space-y-1 text-gray-600">
                <li><code class="text-xs">isp:poll-olt-intelligence</code> — SNMP poll all OLTs every 10 min</li>
                <li><code class="text-xs">isp:process-netflow-inbox</code> — import JSON from <code class="text-xs">storage/app/netflow/inbox/</code></li>
                <li>POST <code class="text-xs">/api/webhooks/netflow-ingest</code> with header <code class="text-xs">X-Netflow-Secret</code></li>
                <li>ONU optical/status: set <code class="text-xs">devices.meta</code> keys or run meta sync (<code class="text-xs">isp:sync-onu-status-from-meta</code>)</li>
            </ul>
        </div>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
