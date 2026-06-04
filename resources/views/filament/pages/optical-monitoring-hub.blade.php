<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/optical-noc.css') }}?v=10">

    <div class="isp-optical-page-shell isp-optical-noc space-y-4">
        @php $noc = $this->getNocPayload(); $stats = $noc; @endphp
        <div class="isp-optical-db-banner">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-blue-200">GPON NOC</p>
                <h2 class="text-xl font-bold text-white">Optical command center</h2>
                <p class="mt-1 text-sm text-blue-100/90">RX/TX dBm · Temp/Voltage · PON + MikroTik VLAN · OLT health</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded bg-emerald-500/25 px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-100" title="16 PON = 8×Aveis + 8×Bdcom. Aveis MikroTik/VLAN needs ONU↔subscriber link.">PON-16 2026-06-02</span>
                <span class="rounded bg-white/15 px-3 py-1 text-xs font-semibold text-white">{{ number_format($noc['olt_total'] ?? 0) }} OLT</span>
                <span class="rounded bg-white/15 px-3 py-1 text-xs font-semibold text-white">{{ number_format($stats['total_onus']) }} ONU</span>
                <span class="rounded bg-emerald-500/30 px-3 py-1 text-xs font-semibold text-emerald-100">{{ number_format($stats['online_onus']) }} online</span>
                <span class="rounded bg-rose-500/30 px-3 py-1 text-xs font-semibold text-rose-100">{{ number_format($stats['open_alerts'] ?? 0) }} alarms</span>
            </div>
        </div>

        @php
            $monitorTabs = [
                'database' => 'ONU database',
                'olt' => 'OLT health',
                'topology' => 'Topology',
                'charts' => 'Charts',
                'pon' => 'PON stats',
                'ai' => 'AI',
                'alerts' => 'Alerts',
            ];
        @endphp

        <nav class="flex flex-wrap gap-2 border-b border-gray-200 pb-3 dark:border-gray-700" aria-label="GPON tools">
            <span class="w-full text-xs font-bold uppercase text-gray-500">GPON tools</span>
            @foreach ($monitorTabs as $tab => $label)
                <a href="{{ $this->monitorTabUrl($tab) }}"
                    @class([
                        'rounded-lg px-3 py-1.5 text-xs font-semibold no-underline',
                        'bg-slate-800 text-white' => $monitorTab === $tab,
                        'bg-gray-100 text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $monitorTab !== $tab,
                    ])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if ($monitorTab === 'database')
            @include('filament.pages.partials.optical-database-table')
        @endif

        @if ($monitorTab === 'olt')
            @php $oltHealth = $this->getOltHealthPayload(); @endphp
            <div class="isp-optical-noc__chart-card isp-optical-olt-health-wrap overflow-x-auto">
                <table class="isp-optical-olt-health-table w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase text-gray-500">
                            <th class="py-2">OLT</th>
                            <th>Driver</th>
                            <th>IP</th>
                            <th>CPU</th>
                            <th>RAM</th>
                            <th>Temp</th>
                            <th>Fan</th>
                            <th>Power</th>
                            <th>Uptime</th>
                            <th>ONUs</th>
                            <th>Health</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($oltHealth['olts'] ?? [] as $olt)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td data-label="OLT" class="py-2 font-medium">{{ $olt['name'] }}</td>
                                <td data-label="Driver" class="py-2 text-xs">{{ strtoupper(str_replace('_', ' ', (string) ($olt['driver'] ?? '—'))) }}</td>
                                <td data-label="IP" class="py-2 font-mono text-xs">{{ $olt['management_ip'] ?? '—' }}</td>
                                <td data-label="CPU" class="py-2">{{ $olt['cpu_percent'] ?? '—' }}%</td>
                                <td data-label="RAM" class="py-2">{{ $olt['memory_percent'] ?? '—' }}%</td>
                                <td data-label="Temp" class="py-2">{{ isset($olt['temperature_c']) ? $olt['temperature_c'].' °C' : '—' }}</td>
                                <td data-label="Fan" class="py-2 text-xs">{{ $olt['fan_status'] ?? '—' }}</td>
                                <td data-label="Power" class="py-2 text-xs">{{ $olt['power_supply_status'] ?? '—' }}</td>
                                <td data-label="Uptime" class="py-2 text-xs">{{ $olt['uptime_human'] ?? '—' }}</td>
                                <td data-label="ONUs" class="py-2">{{ $olt['onus_online'] ?? 0 }}/{{ $olt['onus_total'] ?? 0 }}</td>
                                <td data-label="Health" class="py-2">{{ $olt['health_score'] ?? '—' }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="py-6 text-center text-gray-500">No OLT — Add OLT from header.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($monitorTab === 'topology')
            @include('filament.pages.partials.optical-topology-tab', ['topology' => $this->getTopologyPayload()])
        @endif

        @if ($monitorTab === 'charts')
            @php $trend = $this->getTrend24hPayload(); @endphp
            <div class="grid gap-4 lg:grid-cols-2 isp-optical-noc__chart-card">
                <div><p class="mb-2 text-sm font-semibold">Avg RX 24h</p><canvas id="isp-tenant-rx-chart" height="180"></canvas></div>
                <div><p class="mb-2 text-sm font-semibold">Weak ONU 24h</p><canvas id="isp-tenant-weak-chart" height="180"></canvas></div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof Chart === 'undefined') return;
                    const labels = @json($trend['labels']);
                    new Chart(document.getElementById('isp-tenant-rx-chart'), {
                        type: 'line',
                        data: { labels, datasets: [{ label: 'Avg RX', data: @json($trend['avg_rx']), borderColor: '#10b981', tension: 0.3, spanGaps: true }] },
                        options: { responsive: true },
                    });
                    new Chart(document.getElementById('isp-tenant-weak-chart'), {
                        type: 'bar',
                        data: { labels, datasets: [{ label: 'Weak', data: @json($trend['weak_count']), backgroundColor: 'rgba(251,191,36,0.6)' }] },
                        options: { responsive: true },
                    });
                });
            </script>
        @endif

        @if ($monitorTab === 'pon')
            {{-- isp-pon-table-v3: OLT · MikroTik · VLAN · port name · index · ONUs · weak · avg RX --}}
            @include('filament.pages.partials.optical-pon-stats-table')
        @endif

        @if ($monitorTab === 'ai')
            @php $aiWarnings = $this->getAiWarningsPayload(); @endphp
            <div class="space-y-2">
                @forelse ($aiWarnings as $warn)
                    <div class="isp-optical-noc__ai-row"><p class="text-sm">{{ $warn['summary'] }}</p></div>
                @empty
                    <p class="text-sm text-gray-500">No AI warnings.</p>
                @endforelse
            </div>
        @endif

        @if ($monitorTab === 'alerts')
            @include('filament.pages.partials.optical-alerts-table')
        @endif
    </div>
</x-filament-panels::page>
