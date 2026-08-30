<div class="oc-desk" x-data="{ onu: null }">
    <x-slot name="header">{{ __('Online Clients') }}</x-slot>

    @if($trafficId)
        <div wire:poll.2000ms="pollTraffic" class="d-none"></div>
    @endif

    <div class="oc-toolbar">
        <div>
            <h1 class="oc-title">{{ __('Online Clients') }}</h1>
            <p class="oc-sub mb-0">{{ __('Login, logout, ONU, live speed, and session / daily / monthly GB from the last MikroTik sync.') }}</p>
        </div>
        <div class="oc-toolbar-actions">
            <span class="oc-pill oc-pill-on">{{ __('Online') }} {{ $onlineCount }}</span>
            <span class="oc-pill oc-pill-off">{{ __('Offline') }} {{ $offlineCount }}</span>
        </div>
    </div>

    <div class="oc-card oc-filters">
        <input type="search" class="form-control form-control-sm oc-input" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('Search user / IP / name / MAC') }}">
        <select class="form-select form-select-sm oc-input" wire:model.live="filter">
            <option value="online">{{ __('Online') }}</option>
            <option value="offline">{{ __('Offline') }}</option>
            <option value="all">{{ __('All') }}</option>
        </select>
        <select class="form-select form-select-sm oc-input" wire:model.live="routerFilter">
            <option value="">{{ __('All routers') }}</option>
            @foreach ($routers as $rn)
                <option value="{{ $rn }}">{{ $rn }}</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-sm oc-btn-ink" wire:click="refreshOnline" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="refreshOnline"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Refresh') }}</span>
            <span wire:loading wire:target="refreshOnline" class="spinner-border spinner-border-sm"></span>
        </button>
    </div>

    <div class="oc-card d-none d-lg-block">
        <div class="table-responsive">
            <table class="table oc-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Network') }}</th>
                        <th>{{ __('ONU') }}</th>
                        <th>{{ __('Traffic used') }}</th>
                        <th>{{ __('Login / Uptime') }}</th>
                        <th>{{ __('Logout / Reason') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @include('livewire.partials.online-client-row', ['row' => $row, 'compact' => false])
                    @empty
                        <tr>
                            <td colspan="8" class="text-center oc-muted py-4">{{ __('No PPP users found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-lg-none oc-mobile">
        @forelse ($rows as $row)
            @php
                $isOnline = !empty($row->uptime);
                $c = $row->customer;
                $onu = $c?->onus?->sortByDesc('last_polled_at')->first();
            @endphp
            <div class="oc-card oc-mcard">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="fw-bold">{{ $c->customer_name ?? $row->username }}</div>
                        <div class="oc-mono small">{{ $row->username }} @if($c) · {{ $c->customer_unique_id }} @endif</div>
                    </div>
                    @if($isOnline)
                        <span class="badge bg-success">{{ __('Connected') }}</span>
                    @else
                        <span class="badge bg-secondary">{{ __('Offline') }}</span>
                    @endif
                </div>
                <div class="oc-grid small">
                    <div><span class="oc-muted">{{ __('Phone') }}</span><div>{{ $c->mobile ?? '—' }}</div></div>
                    <div><span class="oc-muted">{{ __('Package') }}</span><div>{{ $c?->package?->package ?? $row->profile }}</div></div>
                    <div><span class="oc-muted">{{ __('IP') }}</span>
                        <div>
                            @if($isOnline && $row->ppp_remote_ip)
                                <button type="button" class="btn btn-link p-0 oc-link" wire:click="openTraffic({{ $row->id }})">{{ $row->ppp_remote_ip }}</button>
                            @else
                                {{ $row->ppp_remote_ip ?: '—' }}
                            @endif
                        </div>
                    </div>
                    <div><span class="oc-muted">{{ __('ONU RX/TX') }}</span>
                        <div>{{ $onu?->rx_power_dbm ? number_format((float) $onu->rx_power_dbm, 1).' / '.number_format((float) ($onu->tx_power_dbm ?? 0), 1).' dBm' : '—' }}</div>
                    </div>
                    <div class="oc-usage-wrap">
                        @include('livewire.partials.traffic-usage-pills', ['usage' => $usages[$row->id] ?? [], 'compact' => true])
                    </div>
                    <div><span class="oc-muted">{{ __('Login') }}</span>
                        <div>
                            @if($isOnline)
                                {{ \Carbon\Carbon::parse($row->uptime)->format('d/m/Y h:i:s A') }}
                                <div class="text-success">{{ \App\Livewire\OnlineClients::sessionDuration($row->uptime) }}</div>
                            @else — @endif
                        </div>
                    </div>
                    <div><span class="oc-muted">{{ __('Logout / Reason') }}</span>
                        <div>
                            @if(!$isOnline)
                                {{ $row->last_logged_out ? \Carbon\Carbon::parse($row->last_logged_out)->format('d/m/Y h:i A') : '—' }}
                                @if($row->last_disconnect_reason)
                                    <div class="text-warning">{{ \App\Livewire\OnlineClients::disconnectLabel($row->last_disconnect_reason) }}</div>
                                @endif
                            @else — @endif
                        </div>
                    </div>
                </div>
                <div class="oc-actions mt-2">
                    <button type="button" class="btn btn-sm oc-icon" @click="onu = {{ \Illuminate\Support\Js::from([
                        'id' => $row->id,
                        'user' => $row->username,
                        'canSync' => (bool) $c,
                        'rx' => $onu?->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 1) : '',
                        'tx' => $onu?->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 1) : '',
                        'pon' => $onu?->pon_port ?: '',
                        'olt' => $onu?->olt_name ?: '',
                        'mac' => $onu?->mac_address ?: '',
                        'status' => $onu?->oper_status ?: '',
                        'polled' => $onu?->last_polled_at ? $onu->last_polled_at->diffForHumans() : '',
                    ]) }}" title="{{ __('ONU details') }}"><i class="bi bi-diagram-3"></i></button>
                    <button type="button" class="btn btn-sm oc-icon" wire:click="refreshOne({{ $row->id }})" wire:loading.attr="disabled" title="{{ __('Refresh') }}"><i class="bi bi-arrow-repeat"></i></button>
                    <button type="button" class="btn btn-sm oc-icon" @disabled(!$isOnline) wire:click="openTraffic({{ $row->id }})" title="{{ __('Live traffic') }}"><i class="bi bi-bar-chart-line"></i></button>
                    @if($c)
                        <a class="btn btn-sm oc-icon" href="{{ route('customers.show', encrypt($c->customer_unique_id)) }}" title="{{ __('Profile') }}"><i class="bi bi-person-badge"></i></a>
                    @endif
                </div>
            </div>
        @empty
            <div class="oc-card oc-muted text-center py-4">{{ __('No PPP users found.') }}</div>
        @endforelse
    </div>

    <div class="mt-3">{{ $rows->links() }}</div>

    @if($trafficId)
        <div class="oc-overlay" wire:click.self="closeTraffic">
            <div class="oc-modal">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-bold">{{ __('Live traffic') }}</div>
                        <div class="small oc-muted oc-mono">{{ $trafficUser }} · {{ $trafficInterface }} @ {{ $trafficRouter }}</div>
                    </div>
                    <button type="button" class="btn btn-sm oc-icon" wire:click="closeTraffic"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="tu-modal-stats mb-3">
                    @include('livewire.partials.traffic-usage-pills', ['usage' => $trafficUsage ?? [], 'compact' => false])
                    <div class="small oc-muted mt-1">
                        {{ __('Download') }} {{ $trafficUsage['session_tx_label'] ?? '0 B' }}
                        · {{ __('Upload') }} {{ $trafficUsage['session_rx_label'] ?? '0 B' }}
                    </div>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-success"><i class="bi bi-arrow-down-circle me-1"></i>{{ __('Live download') }}</span>
                    <strong class="text-success" id="oc-rx-label">{{ number_format($rxSpeed / 1_000_000, 2) }} Mbps</strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-primary"><i class="bi bi-arrow-up-circle me-1"></i>{{ __('Live upload') }}</span>
                    <strong class="text-primary" id="oc-tx-label">{{ number_format($txSpeed / 1_000_000, 2) }} Mbps</strong>
                </div>
                <div wire:ignore class="oc-chart" x-data="onlineClientTrafficChart()" x-init="$nextTick(() => initChart())"
                     @oc-traffic-updated.window="updateTraffic($event.detail)">
                    <div x-ref="chartContainer" style="width:100%;min-height:220px;height:220px;"></div>
                </div>
            </div>
        </div>
    @endif

    <div class="oc-overlay" x-show="onu" x-cloak @click.self="onu = null">
        <div class="oc-modal">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fw-bold">{{ __('ONU details') }} · <span x-text="onu?.user"></span></div>
                <button type="button" class="btn btn-sm oc-icon" @click="onu = null"><i class="bi bi-x-lg"></i></button>
            </div>
            <template x-if="onu && onu.rx">
                <div>
                    <div class="oc-grid">
                        <div><span class="oc-muted">{{ __('RX') }}</span><div class="fw-bold"><span x-text="onu.rx"></span> dBm</div></div>
                        <div><span class="oc-muted">{{ __('TX') }}</span><div class="fw-bold"><span x-text="onu.tx || '—'"></span> dBm</div></div>
                        <div><span class="oc-muted">{{ __('PON') }}</span><div x-text="onu.pon || '—'"></div></div>
                        <div><span class="oc-muted">{{ __('OLT') }}</span><div x-text="onu.olt || '—'"></div></div>
                        <div><span class="oc-muted">{{ __('MAC') }}</span><div class="oc-mono" x-text="onu.mac || '—'"></div></div>
                        <div><span class="oc-muted">{{ __('Status') }}</span><div x-text="onu.status || '—'"></div></div>
                    </div>
                    <div class="small oc-muted mt-2" x-show="onu.polled">{{ __('Last polled') }}: <span x-text="onu.polled"></span></div>
                </div>
            </template>
            <div class="oc-muted mb-3" x-show="onu && !onu.rx">{{ __('No ONU linked yet. Auto-sync reads this panel OLT and the live PPPoE MAC.') }}</div>
            <button type="button" class="btn btn-sm oc-btn-ink mt-3" x-show="onu?.canSync" @click="$wire.syncOnu(onu.id)">
                <i class="bi bi-arrow-repeat me-1"></i>{{ __('Auto-sync ONU') }}
            </button>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .oc-desk [x-cloak], .oc-desk [x-cloak] { display: none !important; }
        .oc-desk {
            --oc-ink: #1e3a5f;
            --oc-card: #fff;
            --oc-line: #d5dde6;
            --oc-text: #1e293b;
            --oc-muted: #64748b;
            --oc-wash: #f4f6f8;
            color: var(--oc-text);
        }
        [data-bs-theme="dark"] .oc-desk,
        html.dark .oc-desk {
            --oc-ink: #9ec0e6;
            --oc-card: #152033;
            --oc-line: #2c3b52;
            --oc-text: #e8eef5;
            --oc-muted: #94a3b8;
            --oc-wash: #101826;
        }
        .oc-toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; gap:.75rem; margin-bottom:.85rem; }
        .oc-title { font-size:1.15rem; font-weight:700; color:var(--oc-ink); margin:0; }
        .oc-sub, .oc-muted { color:var(--oc-muted); }
        .oc-toolbar-actions { display:flex; gap:.4rem; align-items:center; }
        .oc-card { background:var(--oc-card); border:1px solid var(--oc-line); border-radius:10px; }
        .oc-filters { display:flex; flex-wrap:wrap; gap:.5rem; padding:.7rem .75rem; margin-bottom:.75rem; }
        .oc-input { max-width:100%; width:220px; background:var(--oc-card); color:var(--oc-text); border-color:var(--oc-line); }
        .oc-btn-ink { background:#1e3a5f; color:#fff; border-color:#1e3a5f; }
        [data-bs-theme="dark"] .oc-btn-ink { background:#3d6ea8; border-color:#3d6ea8; }
        .oc-pill { border-radius:999px; padding:.2rem .65rem; font-size:.78rem; font-weight:700; }
        .oc-pill-on { background:#d1fae5; color:#065f46; }
        .oc-pill-off { background:#e2e8f0; color:#334155; }
        [data-bs-theme="dark"] .oc-pill-on { background:#064e3b; color:#6ee7b7; }
        [data-bs-theme="dark"] .oc-pill-off { background:#1e293b; color:#cbd5e1; }
        .oc-table { font-size:.82rem; color:var(--oc-text); }
        .oc-table thead th { background:var(--oc-wash); color:var(--oc-ink); border-color:var(--oc-line); white-space:nowrap; }
        .oc-table td { border-color:var(--oc-line); }
        .oc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .oc-link { color:#0d6efd; text-decoration:none; font-weight:600; }
        .oc-actions { display:flex; gap:.35rem; justify-content:flex-end; }
        .oc-icon { width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--oc-line); background:var(--oc-card); color:#198754; border-radius:8px; }
        .oc-icon:disabled { opacity:.4; }
        .oc-mcard { padding:.85rem; margin-bottom:.65rem; }
        .oc-grid { display:grid; grid-template-columns:1fr 1fr; gap:.55rem .75rem; }
        .oc-overlay { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:1080; display:flex; align-items:flex-end; justify-content:center; padding:1rem; }
        @media (min-width:768px) { .oc-overlay { align-items:center; } }
        .oc-modal { width:min(720px,100%); background:var(--oc-card); color:var(--oc-text); border:1px solid var(--oc-line); border-radius:14px; padding:1rem; max-height:90vh; overflow:auto; }
        .oc-chart { border:1px solid var(--oc-line); border-radius:10px; background:var(--oc-wash); }
        .tu-pills { display:flex; flex-direction:column; gap:.2rem; }
        .tu-pills-compact .tu-pill { padding:.1rem 0; }
        .tu-pill { display:flex; justify-content:space-between; gap:.5rem; line-height:1.25; }
        .tu-pill span { color:var(--oc-muted); font-size:.72rem; text-transform:uppercase; letter-spacing:.02em; }
        .tu-pill strong { font-size:.8rem; color:var(--oc-text); white-space:nowrap; }
        .tu-pill-month strong { color:#0f766e; }
        .tu-modal-stats { background:var(--oc-wash); border:1px solid var(--oc-line); border-radius:10px; padding:.65rem .75rem; }
        .oc-usage-wrap { grid-column:1 / -1; }
        @media (max-width:575.98px) {
            .oc-input { width:100%; }
            .oc-filters { flex-direction:column; }
            .oc-grid { grid-template-columns:1fr; }
        }
    </style>
@endpush

@script
<script>
    window.onlineClientTrafficChart = function () {
        return {
            chart: null,
            dataRx: [],
            dataTx: [],
            maxPoints: 120,
            initChart() {
                if (!window.ApexCharts || !this.$refs.chartContainer) return;
                if (this.chart) { this.chart.destroy(); this.chart = null; }
                this.dataRx = []; this.dataTx = [];
                const now = Date.now();
                for (let i = 60; i > 0; i--) {
                    this.dataRx.push([now - (i * 2000), 0]);
                    this.dataTx.push([now - (i * 2000), 0]);
                }
                const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark'
                    || document.documentElement.classList.contains('dark');
                this.chart = new ApexCharts(this.$refs.chartContainer, {
                    series: [
                        { name: '{{ __("Download") }}', data: this.dataRx },
                        { name: '{{ __("Upload") }}', data: this.dataTx },
                    ],
                    chart: { type: 'area', height: 220, animations: { enabled: true, easing: 'linear', dynamicAnimation: { speed: 800 } }, toolbar: { show: false }, zoom: { enabled: false } },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    colors: ['#198754', '#0d6efd'],
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', width: 2 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] } },
                    xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'HH:mm:ss' } },
                    yaxis: { min: 0, labels: { formatter: (v) => v >= 1 ? v.toFixed(1) + ' Mbps' : (v * 1024).toFixed(0) + ' Kbps' } },
                    legend: { position: 'top', horizontalAlign: 'left' },
                    tooltip: { x: { format: 'HH:mm:ss' }, y: { formatter: (v) => v >= 1 ? v.toFixed(2) + ' Mbps' : (v * 1024).toFixed(1) + ' Kbps' } },
                });
                this.chart.render();
            },
            updateTraffic(detail) {
                const evt = Array.isArray(detail) ? detail[0] : detail;
                const rxMbps = (evt?.rx || 0) / 1048576;
                const txMbps = (evt?.tx || 0) / 1048576;
                const now = Date.now();
                const rxLabel = document.getElementById('oc-rx-label');
                const txLabel = document.getElementById('oc-tx-label');
                if (rxLabel) rxLabel.textContent = rxMbps >= 1 ? rxMbps.toFixed(2) + ' Mbps' : (rxMbps * 1024).toFixed(0) + ' Kbps';
                if (txLabel) txLabel.textContent = txMbps >= 1 ? txMbps.toFixed(2) + ' Mbps' : (txMbps * 1024).toFixed(0) + ' Kbps';
                if (!this.chart) return;
                this.dataRx.push([now, rxMbps]);
                this.dataTx.push([now, txMbps]);
                if (this.dataRx.length > this.maxPoints) this.dataRx.shift();
                if (this.dataTx.length > this.maxPoints) this.dataTx.shift();
                this.chart.updateSeries([{ data: this.dataRx }, { data: this.dataTx }]);
            },
        };
    };
</script>
@endscript
