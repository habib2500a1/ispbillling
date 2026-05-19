@extends('portal.layout')

@section('title', 'Dashboard')

@php
    $conn = $dash['connection'] ?? [];
    $traffic = $dash['traffic'] ?? [];
    $onu = $dash['onu'] ?? [];
    $bill = $dash['billing'] ?? [];
    $pkg = $dash['package'] ?? null;
    $onuColor = match ($onu['color'] ?? 'gray') {
        'success' => 'emerald',
        'warning' => 'amber',
        'danger' => 'rose',
        default => 'slate',
    };
    $isOnline = (bool) ($conn['online'] ?? false);
@endphp

@section('content')
    <div class="portal-dash-hero">
        <div>
            <h1 class="portal-dash-hero__title">Hello, {{ $customer->name }}</h1>
            <p class="portal-dash-hero__sub">
                <span class="portal-dash-hero__company">{{ $companyName }}</span>
                · {{ $customer->customer_code }} · Live dashboard
            </p>
        </div>
        <p id="dash-updated" class="portal-live-badge">Live</p>
    </div>

    <x-portal-marquee :items="$portalMarquee ?? collect()" variant="portal" />
    <x-portal-notices-banner :notices="$portalNotices ?? collect()" variant="portal" />

    @if (($movieServers ?? collect())->isNotEmpty())
        <div style="margin-top: 1.25rem;">
            <x-movie-servers-showcase :servers="$movieServers" variant="portal" />
        </div>
    @endif

    @if ($outages->isNotEmpty())
        <div class="portal-panel" style="margin-top: 1rem; border-color: rgba(245, 158, 11, 0.4); background: rgba(254, 243, 199, 0.15);">
            <p class="portal-panel__title">Area notices</p>
            <ul class="portal-pro-card__meta" style="margin-top: 0.5rem; list-style: disc; padding-left: 1.25rem;">
                @foreach ($outages as $o)
                    <li>{{ $o->title }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="portal-dash-grid">
        <article class="portal-pro-card portal-pro-card--connection">
            <header class="portal-pro-card__head">
                <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
                    <span class="portal-pro-card__icon" aria-hidden="true">📡</span>
                    <div>
                        <p class="portal-pro-card__label">Connection</p>
                        <p class="portal-pro-card__hint">PPPoE / session</p>
                    </div>
                </div>
                <span id="live-dot" class="portal-pro-status-dot {{ $isOnline ? 'portal-pro-status-dot--online' : 'portal-pro-status-dot--offline' }}" aria-hidden="true"></span>
            </header>
            <p id="stat-connection" class="portal-pro-card__value {{ $isOnline ? 'portal-pro-value-online' : 'portal-pro-value-offline' }}">
                {{ $conn['status_label'] ?? '—' }}
            </p>
            <p id="stat-ppp" class="portal-pro-card__meta">{{ $conn['router_status'] ?? '—' }}</p>
            <p class="portal-pro-card__meta">IP: <span id="stat-ip" class="portal-mono">{{ $conn['framed_ip'] ?? '—' }}</span></p>
            <p class="portal-pro-card__meta">Uptime: <span id="stat-uptime">{{ $conn['session_uptime'] ?? '—' }}</span></p>
            <p class="portal-pro-card__meta">Last online: <span id="stat-last">{{ $conn['last_online'] ?? '—' }}</span></p>
        </article>

        <article class="portal-pro-card portal-pro-card--speed">
            <header class="portal-pro-card__head">
                <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
                    <span class="portal-pro-card__icon" aria-hidden="true">⚡</span>
                    <div>
                        <p class="portal-pro-card__label">Live speed</p>
                        <p class="portal-pro-card__hint">Real-time traffic</p>
                    </div>
                </div>
            </header>
            <p class="portal-pro-card__meta">↓ <span id="stat-down" class="portal-pro-card__value portal-pro-card__value--sm" style="display: inline;">{{ $traffic['download_human'] ?? '—' }}</span></p>
            <p class="portal-pro-card__meta" style="margin-top: 0.35rem;">↑ <span id="stat-up" class="portal-pro-card__value portal-pro-card__value--sm" style="display: inline;">{{ $traffic['upload_human'] ?? '—' }}</span></p>
            <a href="{{ route('portal.usage.index') }}" class="portal-pro-card__link">Open traffic monitor →</a>
        </article>

        <article class="portal-pro-card portal-pro-card--onu">
            <header class="portal-pro-card__head">
                <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
                    <span class="portal-pro-card__icon" aria-hidden="true">📶</span>
                    <div>
                        <p class="portal-pro-card__label">ONU signal</p>
                        <p class="portal-pro-card__hint">Fiber optics</p>
                    </div>
                </div>
            </header>
            @if ($onu['linked'] ?? false)
                <p class="portal-pro-card__value">
                    <span id="stat-rx">{{ $onu['rx_dbm'] !== null ? $onu['rx_dbm'].' dBm' : '—' }}</span>
                </p>
                <p class="portal-pro-card__meta">TX: <span id="stat-tx" class="portal-mono">{{ $onu['tx_dbm'] !== null ? $onu['tx_dbm'].' dBm' : '—' }}</span></p>
                <p id="stat-signal-level" class="portal-status-pill portal-signal-{{ $onuColor }}" style="margin-top: 0.5rem;">
                    {{ $onu['rx_level_label'] ?? 'Unknown' }}
                </p>
                <p class="portal-pro-card__meta">Stability: <span id="stat-stability">{{ $onu['stability_percent'] ?? 0 }}%</span></p>
            @else
                <p class="portal-pro-card__meta">{{ $onu['hint'] ?? 'ONU not linked' }}</p>
                <a href="{{ route('portal.tickets.create') }}" class="portal-pro-card__link">Contact support</a>
            @endif
            <a href="{{ route('portal.onu.index') }}" class="portal-pro-card__link">ONU details →</a>
        </article>

        <article class="portal-pro-card portal-pro-card--billing">
            <header class="portal-pro-card__head">
                <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
                    <span class="portal-pro-card__icon" aria-hidden="true">৳</span>
                    <div>
                        <p class="portal-pro-card__label">Current due</p>
                        <p class="portal-pro-card__hint">Outstanding balance</p>
                    </div>
                </div>
            </header>
            <p id="stat-due" class="portal-pro-card__value portal-pro-value-due">{{ number_format($bill['total_due'] ?? 0, 0) }} BDT</p>
            <p class="portal-pro-card__meta">Due date: <span id="stat-due-date">{{ $bill['next_due_date'] ?? '—' }}</span></p>
            <a href="{{ route('portal.bills.index') }}" class="portal-btn-primary portal-pro-card__link" style="color: #fff; margin-top: 0.85rem;">Pay bill</a>
        </article>

        <article class="portal-pro-card portal-pro-card--wallet">
            <header class="portal-pro-card__head">
                <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
                    <span class="portal-pro-card__icon" aria-hidden="true">💳</span>
                    <div>
                        <p class="portal-pro-card__label">Wallet & package</p>
                        <p class="portal-pro-card__hint">Prepaid balance</p>
                    </div>
                </div>
            </header>
            <p id="stat-wallet" class="portal-pro-card__value portal-pro-value-wallet">{{ number_format($bill['wallet_balance'] ?? 0, 2) }} BDT</p>
            @if ($pkg)
                <p class="portal-pro-card__meta" style="font-weight: 700; color: var(--portal-text);">{{ $pkg['name'] }}</p>
                <p class="portal-pro-card__meta">{{ $pkg['download_mbps'] }} Mbps · Expires {{ $pkg['expires_at'] ?? '—' }}</p>
            @endif
        </article>

        <article class="portal-pro-card portal-pro-card--actions">
            <header class="portal-pro-card__head">
                <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
                    <span class="portal-pro-card__icon" aria-hidden="true">✦</span>
                    <div>
                        <p class="portal-pro-card__label">Quick actions</p>
                        <p class="portal-pro-card__hint">Shortcuts</p>
                    </div>
                </div>
            </header>
            <div class="portal-pro-card__actions">
                <a href="{{ route('portal.speed-test.index') }}" class="portal-pro-chip" style="background: rgba(79, 70, 229, 0.12); color: #4338ca;">Speed test</a>
                <a href="{{ route('portal.tickets.create') }}" class="portal-pro-chip" style="background: rgba(245, 158, 11, 0.15); color: #b45309;">Support</a>
                <a href="{{ route('portal.notifications.index') }}" class="portal-pro-chip" style="background: rgba(124, 58, 237, 0.12); color: #6d28d9;">
                    Alerts @if(($dash['notifications_count'] ?? 0) > 0)({{ $dash['notifications_count'] }})@endif
                </a>
                @if (config('portal.whatsapp_url'))
                    <a href="{{ config('portal.whatsapp_url') }}" target="_blank" rel="noopener" class="portal-pro-chip" style="background: rgba(16, 185, 129, 0.15); color: #047857;">WhatsApp</a>
                @endif
            </div>
        </article>
    </div>

    <div class="portal-panel">
        <h2 class="portal-panel__title">Live bandwidth (12h)</h2>
        <canvas id="dash-chart" style="margin-top: 0.85rem; width: 100%; height: 10rem;" height="140"></canvas>
    </div>

    <div class="portal-panel">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <h2 class="portal-panel__title">Recent bills</h2>
            <a href="{{ route('portal.bills.index') }}" class="portal-pro-card__link" style="margin: 0;">View all →</a>
        </div>
        <div class="portal-table-wrap" style="margin-top: 0.75rem;">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th style="text-align: right;">Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentInvoices as $inv)
                        @php $due = round((float) $inv->total - (float) $inv->amount_paid, 2); @endphp
                        <tr>
                            <td><a href="{{ route('portal.invoices.show', $inv) }}" class="portal-link">{{ $inv->invoice_number }}</a></td>
                            <td style="text-align: right; font-weight: 700; color: {{ $due > 0 ? '#e11d48' : '#059669' }};">{{ number_format($due, 2) }}</td>
                            <td><span class="portal-status-pill">{{ $inv->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="portal-empty-state" style="border: none;">No bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            const pollMs = {{ (int) config('portal.poll_seconds', 5) * 1000 }};
            let dash = @json($dash);
            const ctx = document.getElementById('dash-chart');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dash.traffic.chart.labels,
                    datasets: [
                        { label: 'Download Mbps', data: dash.traffic.chart.download_mbps, borderColor: '#d97706', tension: 0.3 },
                        { label: 'Upload Mbps', data: dash.traffic.chart.upload_mbps, borderColor: '#0284c7', tension: 0.3 },
                    ],
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } }, animation: { duration: 400 } },
            });

            function setOnline(on) {
                const dot = document.getElementById('live-dot');
                const stat = document.getElementById('stat-connection');
                dot.className = 'portal-pro-status-dot ' + (on ? 'portal-pro-status-dot--online' : 'portal-pro-status-dot--offline');
                stat.textContent = on ? 'Online' : 'Offline';
                stat.className = 'portal-pro-card__value ' + (on ? 'portal-pro-value-online' : 'portal-pro-value-offline');
            }

            async function refresh() {
                try {
                    const res = await fetch(@json(route('portal.dashboard.live')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) return;
                    dash = await res.json();
                    const c = dash.connection, t = dash.traffic, o = dash.onu, b = dash.billing;
                    setOnline(c.online);
                    document.getElementById('stat-ppp').textContent = c.router_status || '—';
                    document.getElementById('stat-ip').textContent = c.framed_ip || '—';
                    document.getElementById('stat-uptime').textContent = c.session_uptime || '—';
                    document.getElementById('stat-last').textContent = c.last_online || '—';
                    document.getElementById('stat-down').textContent = t.download_human || '—';
                    document.getElementById('stat-up').textContent = t.upload_human || '—';
                    if (o.linked) {
                        document.getElementById('stat-rx').textContent = o.rx_dbm != null ? o.rx_dbm + ' dBm' : '—';
                        document.getElementById('stat-tx').textContent = o.tx_dbm != null ? o.tx_dbm + ' dBm' : '—';
                        document.getElementById('stat-signal-level').textContent = o.rx_level_label || '—';
                        document.getElementById('stat-stability').textContent = (o.stability_percent || 0) + '%';
                    }
                    document.getElementById('stat-due').textContent = Number(b.total_due || 0).toLocaleString() + ' BDT';
                    document.getElementById('stat-due-date').textContent = b.next_due_date || '—';
                    document.getElementById('stat-wallet').textContent = Number(b.wallet_balance || 0).toFixed(2) + ' BDT';
                    chart.data.labels = t.chart.labels;
                    chart.data.datasets[0].data = t.chart.download_mbps;
                    chart.data.datasets[1].data = t.chart.upload_mbps;
                    chart.update();
                    document.getElementById('dash-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
                } catch (e) {}
            }
            setInterval(refresh, pollMs);
        </script>
    @endpush
@endsection
