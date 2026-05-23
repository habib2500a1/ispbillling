@php
    $stats = $this->getOpticalStatsSafe();
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/optical-noc.css') }}?v=5">

    <div class="isp-optical-page-shell isp-optical-noc space-y-4">
        <div class="isp-optical-db-banner">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-blue-200">ISP Digital style</p>
                <h2 class="text-xl font-bold text-white">Optical Database</h2>
                <p class="mt-1 text-sm text-blue-100/90">Client Code · UserName · OpticalPower · OLTPort · OnuStatus — পুরনো panel এর মতো</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded bg-white/15 px-3 py-1 text-xs font-semibold text-white">{{ number_format($stats['total_onus']) }} ONU</span>
                <span class="rounded bg-emerald-500/30 px-3 py-1 text-xs font-semibold text-emerald-100">{{ number_format($stats['online_onus']) }} online</span>
            </div>
        </div>

        @include('filament.pages.partials.optical-database-table')

        <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
            <span class="w-full text-xs font-bold uppercase text-gray-500">More tools</span>
            @foreach (['olt' => 'OLT health', 'topology' => 'Topology', 'charts' => 'Charts', 'pon' => 'PON stats', 'ai' => 'AI', 'alerts' => 'Alerts'] as $tab => $label)
                <button type="button" wire:click="setMonitorTab('{{ $tab }}')"
                    @class(['rounded-lg px-3 py-1.5 text-xs font-semibold', 'bg-slate-800 text-white' => $monitorTab === $tab, 'bg-gray-100 dark:bg-gray-800' => $monitorTab !== $tab])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($monitorTab === 'olt')
            @php $oltHealth = $this->getOltHealthPayload(); @endphp
            <div class="isp-optical-noc__chart-card overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b text-xs uppercase text-gray-500">
                        <th class="py-2">OLT</th><th>IP</th><th>CPU</th><th>RAM</th><th>ONUs</th><th>Health</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($oltHealth['olts'] ?? [] as $olt)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 font-medium">{{ $olt['name'] }}</td>
                                <td class="py-2">{{ $olt['management_ip'] ?? '—' }}</td>
                                <td class="py-2">{{ $olt['cpu_percent'] ?? '—' }}%</td>
                                <td class="py-2">{{ $olt['memory_percent'] ?? '—' }}%</td>
                                <td class="py-2">{{ $olt['onus_online'] ?? 0 }}/{{ $olt['onus_total'] ?? 0 }}</td>
                                <td class="py-2">{{ $olt['health_score'] ?? '—' }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500">No OLT — Add OLT from header.</td></tr>
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
            @php $ponPorts = $this->getPonPortsPayload(); @endphp
            <div class="isp-optical-noc__chart-card overflow-x-auto text-sm">
                <table class="w-full">
                    <thead><tr class="border-b text-xs uppercase text-gray-500">
                        <th class="py-2">OLT</th><th>PON</th><th>ONUs</th><th>Avg RX</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($ponPorts as $pon)
                            <tr class="border-b"><td class="py-2">{{ $pon->olt?->display_name }}</td>
                                <td>C{{ $pon->card_no }}/P{{ $pon->pon_no }}</td>
                                <td>{{ $pon->onu_online }}/{{ $pon->onu_total }}</td>
                                <td>{{ $pon->avg_rx_dbm }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
