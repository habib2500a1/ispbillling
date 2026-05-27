@php
    $d = $this->getWallData();
    $n = $d['noc'];
    $g = $d['gpon'];
    $s = $d['support'];
@endphp

<div class="isp-noc-wall" wire:poll.15s id="isp-noc-wall">
    <header class="isp-noc-wall__header">
        <div class="isp-noc-wall__head-copy">
            <p class="isp-noc-wall__eyebrow">Fullscreen operations</p>
            <h1>{{ config('isp.company_name') }} · Live NOC</h1>
        </div>
        <span class="isp-noc-wall__clock" id="noc-clock">{{ now()->format('H:i:s') }}</span>
        <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="isp-noc-wall__exit">Exit wall</a>
    </header>

    <div class="isp-noc-wall__grid">
        <div class="isp-noc-tile isp-noc-tile--cyan">
            <span class="isp-noc-tile__label">PPPoE online</span>
            <span class="isp-noc-tile__value" data-metric="online_now">{{ $n['online_now'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Live subscriber sessions</span>
        </div>
        <div class="isp-noc-tile isp-noc-tile--danger">
            <span class="isp-noc-tile__label">WAN ↓</span>
            <span class="isp-noc-tile__value">{{ $n['wan_bandwidth_mbps'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Mbps ingress</span>
        </div>
        <div class="isp-noc-tile">
            <span class="isp-noc-tile__label">Users ↓</span>
            <span class="isp-noc-tile__value">{{ $n['users_bandwidth_mbps'] ?? $n['bandwidth_mbps'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Mbps subscriber demand</span>
        </div>
        <div class="isp-noc-tile">
            <span class="isp-noc-tile__label">MikroTik</span>
            <span class="isp-noc-tile__value">{{ $n['mikrotik_online'] ?? 0 }}/{{ $n['mikrotik_total'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Routers online / total</span>
        </div>
        <div class="isp-noc-tile">
            <span class="isp-noc-tile__label">OLT</span>
            <span class="isp-noc-tile__value">{{ $n['olts_online'] ?? 0 }}/{{ $n['olts_total'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Optical line terminals</span>
        </div>
        <div class="isp-noc-tile isp-noc-tile--violet">
            <span class="isp-noc-tile__label">ONU online</span>
            <span class="isp-noc-tile__value">{{ $g['online_onus'] ?? 0 }}/{{ $g['total_onus'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Connected optical units</span>
        </div>
        <div class="isp-noc-tile isp-noc-tile--danger">
            <span class="isp-noc-tile__label">Critical ONU</span>
            <span class="isp-noc-tile__value">{{ $g['critical_onus'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Immediate field attention</span>
        </div>
        <div class="isp-noc-tile isp-noc-tile--amber">
            <span class="isp-noc-tile__label">Open tickets</span>
            <span class="isp-noc-tile__value">{{ $s['open'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Support queue load</span>
        </div>
        <div class="isp-noc-tile isp-noc-tile--danger">
            <span class="isp-noc-tile__label">SLA breach</span>
            <span class="isp-noc-tile__value">{{ $s['sla_breached'] ?? 0 }}</span>
            <span class="isp-noc-tile__sub">Past resolve deadline</span>
        </div>
    </div>

    <div class="isp-noc-wall__alerts">
        @forelse ($d['alerts'] as $alert)
            <div class="isp-noc-alert isp-noc-alert--{{ $alert['severity'] }}">{{ $alert['message'] }}</div>
        @empty
            <div class="isp-noc-alert isp-noc-alert--ok">All systems nominal</div>
        @endforelse
    </div>
</div>

<script>
    setInterval(() => {
        const el = document.getElementById('noc-clock');
        if (el) el.textContent = new Date().toLocaleTimeString();
    }, 1000);
</script>
