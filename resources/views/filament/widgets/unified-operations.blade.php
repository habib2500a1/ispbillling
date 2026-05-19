@php
    $b = $billing;
    $n = $noc;
    $g = $gpon;
    $sup = $support;
@endphp

<x-filament-widgets::widget>
    <section class="isp-unified-dash" wire:poll.30s>
        <header class="isp-unified-dash__intro">
            <h2 class="isp-unified-dash__title">All operations — one screen</h2>
            <p class="isp-unified-dash__sub">Billing · Network · GPON · MikroTik · Support — live data, auto-refresh every 30s</p>
        </header>

        <div class="isp-unified-section isp-unified-section--billing">
            <div class="isp-unified-section__head">
                <h3>Billing &amp; revenue</h3>
                <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="isp-unified-section__link">Collection desk →</a>
            </div>
            <div class="isp-unified-metrics">
                <div class="isp-unified-metric"><span>Month collected</span><strong>{{ number_format($b['collected'] ?? 0, 0) }} BDT</strong></div>
                <div class="isp-unified-metric"><span>Today</span><strong>{{ number_format($b['collected_today'] ?? 0, 0) }} BDT</strong></div>
                <div class="isp-unified-metric"><span>Outstanding</span><strong class="isp-unified-metric--warn">{{ number_format($b['outstanding'] ?? 0, 0) }} BDT</strong></div>
                <div class="isp-unified-metric"><span>Due customers</span><strong>{{ number_format($b['due_customers'] ?? 0) }}</strong></div>
                <div class="isp-unified-metric"><span>Open invoices</span><strong>{{ number_format($b['open_invoices'] ?? 0) }}</strong></div>
                <div class="isp-unified-metric"><span>Unpaid subs</span><strong>{{ number_format($b['unpaid'] ?? 0) }}</strong></div>
            </div>
        </div>

        <div class="isp-unified-section isp-unified-section--noc">
            <div class="isp-unified-section__head">
                <h3>Network &amp; NOC</h3>
                <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="isp-unified-section__link">Online clients →</a>
            </div>
            <div class="isp-unified-metrics">
                <div class="isp-unified-metric"><span>PPPoE online</span><strong class="isp-unified-metric--ok">{{ number_format($n['online_now'] ?? 0) }}</strong></div>
                <div class="isp-unified-metric"><span>WAN port ↓</span><strong>{{ $n['wan_bandwidth_mbps'] ?? 0 }} Mbps/s</strong></div>
                <div class="isp-unified-metric"><span>Users ↓</span><strong>{{ $n['users_bandwidth_mbps'] ?? $n['bandwidth_mbps'] ?? 0 }} Mbps/s</strong></div>
                <div class="isp-unified-metric"><span>MikroTik</span><strong>{{ $n['mikrotik_online'] ?? 0 }}/{{ $n['mikrotik_total'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>OLT</span><strong>{{ $n['olts_online'] ?? 0 }}/{{ $n['olts_total'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>ONU (device)</span><strong>{{ $n['onus_online'] ?? 0 }}/{{ $n['onus_total'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Fiber alerts</span><strong class="{{ ($n['fiber_alerts'] ?? 0) > 0 ? 'isp-unified-metric--danger' : '' }}">{{ $n['fiber_alerts'] ?? 0 }}</strong></div>
            </div>
        </div>

        <div class="isp-unified-section isp-unified-section--gpon">
            <div class="isp-unified-section__head">
                <h3>GPON / ONU</h3>
                <a href="{{ \App\Filament\Pages\OpticalMonitoringHub::getUrl() }}" class="isp-unified-section__link">Optical hub →</a>
            </div>
            <div class="isp-unified-metrics">
                <div class="isp-unified-metric"><span>ONU online</span><strong class="isp-unified-metric--ok">{{ $g['online_onus'] ?? 0 }}/{{ $g['total_onus'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Offline</span><strong>{{ $g['offline_onus'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Critical signal</span><strong class="{{ ($g['critical_onus'] ?? 0) > 0 ? 'isp-unified-metric--danger' : '' }}">{{ $g['critical_onus'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Warning</span><strong>{{ $g['warning_onus'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Open alerts</span><strong>{{ $g['open_alerts'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Fiber faults</span><strong class="{{ ($g['fiber_faults'] ?? 0) > 0 ? 'isp-unified-metric--danger' : '' }}">{{ $g['fiber_faults'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Avg RX</span><strong>{{ $g['avg_rx_dbm'] !== null ? $g['avg_rx_dbm'].' dBm' : '—' }}</strong></div>
                <div class="isp-unified-metric"><span>Health</span><strong>{{ $g['avg_health'] ?? 0 }}%</strong></div>
            </div>
        </div>

        <div class="isp-unified-section isp-unified-section--support">
            <div class="isp-unified-section__head">
                <h3>Support &amp; tickets</h3>
                <a href="{{ \App\Filament\Pages\SupportHub::getUrl() }}" class="isp-unified-section__link">Support center →</a>
            </div>
            <div class="isp-unified-metrics">
                <div class="isp-unified-metric"><span>Open tickets</span><strong>{{ $sup['open'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>SLA breached</span><strong class="{{ ($sup['sla_breached'] ?? 0) > 0 ? 'isp-unified-metric--danger' : '' }}">{{ $sup['sla_breached'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Unassigned</span><strong>{{ $sup['unassigned'] ?? 0 }}</strong></div>
                <div class="isp-unified-metric"><span>Critical</span><strong class="{{ ($sup['critical'] ?? 0) > 0 ? 'isp-unified-metric--danger' : '' }}">{{ $sup['critical'] ?? 0 }}</strong></div>
            </div>
        </div>

        @if (count($alerts) > 0)
            <div class="isp-unified-section isp-unified-section--alerts">
                <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Live alerts</h3>
                <ul class="isp-unified-alerts">
                    @foreach ($alerts as $alert)
                        <li class="isp-unified-alerts__item isp-unified-alerts__item--{{ $alert['severity'] ?? 'info' }}">
                            {{ $alert['message'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</x-filament-widgets::widget>
