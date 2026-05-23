@php
    $stats = $this->getOpticalStatsSafe();
    $noc = $this->getNocPayload();
    $trend = $noc['trend_24h'] ?? ['labels' => [], 'avg_rx' => [], 'weak_count' => []];
    $unlinkedCount = \App\Models\Device::withoutGlobalScopes()->where('type', 'onu')->whereNull('customer_id')->count();
    $linkedCount = \App\Models\Device::withoutGlobalScopes()->where('type', 'onu')->whereNotNull('customer_id')->count();
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/optical-noc.css') }}?v=1">

    <div class="isp-optical-noc space-y-5" wire:poll.30s="$refresh">
        <div class="isp-optical-noc__hero">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-cyan-300/90">GPON / EPON · Optical NOC</p>
                    <h2 class="mt-1 text-2xl font-bold">Live optical power monitor</h2>
                    <p class="mt-2 max-w-2xl text-sm text-slate-300">
                        Realtime RX/TX dBm · fiber health · weak ONU detection · AI risk scoring · PON analytics.
                        <span class="text-emerald-400">Excellent ≥ −15</span> ·
                        <span class="text-amber-400">Weak</span> ·
                        <span class="text-rose-400">Critical &lt; −27 dBm</span>
                    </p>
                </div>
                <div class="isp-optical-noc__health-ring" title="Network health">
                    {{ $noc['network_health_score'] ?? $stats['avg_health'] }}%
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-white/10 px-3 py-1">{{ $linkedCount }} linked</span>
                @if($unlinkedCount > 0)
                    <span class="rounded-full bg-amber-500/20 px-3 py-1 text-amber-200">{{ $unlinkedCount }} unlinked</span>
                @endif
                <span class="rounded-full bg-white/10 px-3 py-1">{{ $noc['olt_total'] ?? 0 }} OLT</span>
                <span class="rounded-full bg-cyan-500/20 px-3 py-1 text-cyan-100">30s refresh</span>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
            @foreach ([
                ['Avg RX', $stats['avg_rx_dbm'] !== null ? $stats['avg_rx_dbm'].' dBm' : '—', 'text-emerald-600'],
                ['Health', ($noc['network_health_score'] ?? 0).'%', 'text-cyan-600'],
                ['ONUs', number_format($stats['total_onus']), ''],
                ['Online', number_format($stats['online_onus']), 'text-blue-600'],
                ['Weak', number_format($stats['warning_onus']), 'text-amber-600'],
                ['Critical', number_format($stats['critical_onus']), 'text-rose-600'],
                ['Offline', number_format($stats['offline_onus']), 'text-gray-600'],
                ['Alerts', number_format($stats['open_alerts']), 'text-rose-600'],
            ] as [$label, $value, $color])
                <div class="isp-optical-noc__kpi dark:bg-gray-900/80">
                    <p class="text-[10px] font-bold uppercase text-gray-500">{{ $label }}</p>
                    <p class="isp-optical-noc__kpi-value {{ $color }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach (['onus' => 'Live ONU dBm', 'charts' => 'Signal graphs', 'pon' => 'PON ports', 'ai' => 'AI warnings', 'alerts' => 'Alerts'] as $tab => $label)
                <button type="button" wire:click="setMonitorTab('{{ $tab }}')"
                    @class(['rounded-lg px-4 py-2 text-sm font-semibold', 'bg-cyan-600 text-white shadow' => $monitorTab === $tab, 'bg-gray-100 dark:bg-gray-800' => $monitorTab !== $tab])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($monitorTab === 'charts')
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="isp-optical-noc__chart-card">
                    <p class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Network avg RX (24h)</p>
                    <canvas id="isp-tenant-rx-chart" height="200"></canvas>
                </div>
                <div class="isp-optical-noc__chart-card">
                    <p class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Weak ONU count (24h)</p>
                    <canvas id="isp-tenant-weak-chart" height="200"></canvas>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof Chart === 'undefined') return;
                    const labels = @json($trend['labels']);
                    new Chart(document.getElementById('isp-tenant-rx-chart'), {
                        type: 'line',
                        data: { labels, datasets: [{ label: 'Avg RX dBm', data: @json($trend['avg_rx']), borderColor: '#10b981', tension: 0.3, spanGaps: true }] },
                        options: { responsive: true, scales: { y: { title: { display: true, text: 'dBm' } } } },
                    });
                    new Chart(document.getElementById('isp-tenant-weak-chart'), {
                        type: 'bar',
                        data: { labels, datasets: [{ label: 'Weak ONUs', data: @json($trend['weak_count']), backgroundColor: 'rgba(251, 191, 36, 0.6)' }] },
                        options: { responsive: true },
                    });
                });
            </script>
        @endif

        @if ($monitorTab === 'pon')
            <div class="isp-optical-noc__chart-card overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b text-xs uppercase text-gray-500">
                        <th class="py-2">OLT</th><th>PON</th><th>ONUs</th><th>Avg RX</th><th>Min</th><th>Critical</th><th>Fault %</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($noc['pon_ports'] ?? [] as $pon)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2">{{ $pon->olt?->display_name ?? '—' }}</td>
                                <td class="py-2">C{{ $pon->card_no }}/P{{ $pon->pon_no }}</td>
                                <td class="py-2">{{ $pon->onu_online }}/{{ $pon->onu_total }}</td>
                                <td class="py-2">{{ $pon->avg_rx_dbm !== null ? number_format((float)$pon->avg_rx_dbm, 2).' dBm' : '—' }}</td>
                                <td class="py-2">{{ $pon->min_rx_dbm !== null ? number_format((float)$pon->min_rx_dbm, 2) : '—' }}</td>
                                <td class="py-2 text-rose-600">{{ $pon->onu_critical }}</td>
                                <td class="py-2">{{ $pon->fault_percent }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-gray-500">No PON stats yet — run optical sync.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($monitorTab === 'ai')
            <div class="space-y-3">
                @forelse ($noc['ai_warnings'] ?? [] as $warn)
                    <div @class(['isp-optical-noc__ai-row', 'isp-optical-noc__ai-row--critical' => in_array($warn['risk_level'], ['critical', 'emergency'], true)])>
                        <p class="text-sm font-semibold">Risk {{ $warn['risk_score'] }} · {{ $warn['onu_serial'] ?? 'ONU' }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $warn['summary'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No elevated risk predictions — network looks stable.</p>
                @endforelse
            </div>
        @endif

        @if (in_array($monitorTab, ['onus', 'alerts'], true))
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="mb-3 text-xs text-gray-500">OpticalPower = RX dBm · TX dBm · click row → <strong>Optical graph</strong> for history</p>
                {{ $this->table }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
