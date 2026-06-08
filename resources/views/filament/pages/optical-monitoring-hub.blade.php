@php
    $noc = $this->getNocPayload();
    $stats = $this->getOpticalStatsSafe();
    $signalCards = $this->getSignalQualityCards();
    $faultCards = $this->getFaultCenterCards();
    $insights = $this->getVisualInsights();
    $monitorTabs = [
        'database' => 'ONU status',
        'olt' => 'OLT health',
        'topology' => 'Topology',
        'charts' => 'Signal trends',
        'pon' => 'PON ports',
        'ai' => 'Insights',
        'alerts' => 'Faults',
    ];
@endphp

{!! \App\Support\OltStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-optical-noc-page">
    <div class="olt-oc-pro" wire:loading.class="olt-oc-pro-loading">
        <header class="olt-oc-hero">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest opacity-80">GPON NOC</p>
                <h1 class="olt-oc-hero__title">Optical command center</h1>
                <p class="olt-oc-hero__sub">ONU status · signal quality · PON utilization · OLT health · fault management</p>
                <div class="olt-oc-hero__badges">
                    <span class="olt-oc-hero__badge">{{ number_format($noc['olt_total'] ?? 0) }} OLT</span>
                    <span class="olt-oc-hero__badge olt-oc-hero__badge--ok">{{ number_format($stats['online_onus'] ?? 0) }} online</span>
                    <span class="olt-oc-hero__badge olt-oc-hero__badge--warn">{{ number_format($stats['warning_onus'] ?? 0) }} weak</span>
                    <span class="olt-oc-hero__badge olt-oc-hero__badge--crit">{{ number_format($stats['open_alerts'] ?? 0) }} alerts</span>
                </div>
            </div>
        </header>

        <section aria-label="Signal quality">
            <p class="olt-oc-section__title" style="margin-bottom:0.5rem;">Signal quality center</p>
            <div class="olt-signal-grid">
                @foreach ($signalCards as $card)
                    <article class="olt-signal-card olt-signal-card--{{ $card['tone'] }}">
                        <div class="olt-signal-card__ring">{{ $card['pct'] }}%</div>
                        <span class="olt-signal-card__label">{{ $card['label'] }}</span>
                        <strong class="olt-signal-card__value">{{ $card['value'] }}</strong>
                        <div class="olt-rx-bar" aria-hidden="true">
                            <div @class(['olt-rx-bar__fill', 'olt-rx-bar__fill--'.$card['tone']]) style="width:{{ max(4, $card['pct']) }}%"></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section aria-label="Fault management">
            <p class="olt-oc-section__title" style="margin-bottom:0.5rem;">Fault management</p>
            <div class="olt-fault-grid">
                @foreach ($faultCards as $card)
                    <article @class(['olt-fault-card', 'olt-fault-card--'.$card['tone']])>
                        <span class="olt-fault-card__label">{{ $card['label'] }}</span>
                        <strong class="olt-fault-card__value">{{ $card['value'] }}</strong>
                    </article>
                @endforeach
            </div>
        </section>

        @if ($insights !== [])
            <section class="olt-oc-panel" aria-label="NOC insights">
                <div class="olt-oc-panel__head">Operational insights</div>
                <div style="padding:0.75rem 1rem;display:grid;gap:0.5rem;">
                    @foreach ($insights as $insight)
                        <div class="olt-ai-insight">
                            <div class="olt-ai-insight__icon" aria-hidden="true">◈</div>
                            <p class="olt-ai-insight__text">{{ $insight['message'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <nav class="olt-oc-tabs" aria-label="GPON tools">
            @foreach ($monitorTabs as $tab => $label)
                <a href="{{ $this->monitorTabUrl($tab) }}" @class(['olt-oc-tab', 'olt-oc-tab--active' => $monitorTab === $tab])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="olt-oc-panel">
            @if ($monitorTab === 'database')
                @include('filament.pages.partials.optical-database-table')
            @endif

            @if ($monitorTab === 'olt')
                @php $oltHealth = $this->getOltHealthPayload(); @endphp
                <div class="isp-optical-noc__chart-card isp-optical-olt-health-wrap overflow-x-auto p-3">
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
                <div class="grid gap-4 p-3 lg:grid-cols-2 isp-optical-noc__chart-card">
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
                @include('filament.pages.partials.optical-pon-stats-table')
            @endif

            @if ($monitorTab === 'ai')
                @php $aiWarnings = $this->getAiWarningsPayload(); @endphp
                <div class="space-y-2 p-3">
                    @forelse ($insights as $insight)
                        <div class="olt-ai-insight"><div class="olt-ai-insight__icon" aria-hidden="true">◈</div><p class="olt-ai-insight__text">{{ $insight['message'] }}</p></div>
                    @empty
                        @forelse ($aiWarnings as $warn)
                            <div class="olt-ai-insight"><p class="olt-ai-insight__text">{{ $warn['summary'] }}</p></div>
                        @empty
                            <p class="text-sm text-gray-500">No insights — fleet looks healthy.</p>
                        @endforelse
                    @endforelse
                </div>
            @endif

            @if ($monitorTab === 'alerts')
                @include('filament.pages.partials.optical-alerts-table')
            @endif
        </div>

        <nav class="olt-dock olt-dock--mobile" aria-label="OLT quick nav">
            <div class="olt-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\OltHub::getUrl(), 'label' => 'Center', 'icon' => 'heroicon-o-home'],
                    ['url' => \App\Filament\Resources\OltResource::getUrl(), 'label' => 'OLTs', 'icon' => 'heroicon-o-server-stack'],
                    ['url' => $this->monitorTabUrl('database'), 'label' => 'ONUs', 'icon' => 'heroicon-o-cpu-chip', 'active' => $monitorTab === 'database'],
                    ['url' => $this->monitorTabUrl('pon'), 'label' => 'PON', 'icon' => 'heroicon-o-circle-stack'],
                    ['url' => $this->monitorTabUrl('alerts'), 'label' => 'Alerts', 'icon' => 'heroicon-o-bell-alert'],
                ] as $link)
                    <a href="{{ $link['url'] }}" @class(['olt-dock__link', 'olt-dock__link--active' => ! empty($link['active'])])>
                        <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</x-filament-panels::page>
