@extends('portal.layout')

@section('title', 'Dashboard')

@php
    $conn = $dash['connection'] ?? [];
    $traffic = $dash['traffic'] ?? [];
    $onu = $dash['onu'] ?? [];
    $bill = $dash['billing'] ?? [];
    $pkg = $dash['package'] ?? null;
    $notificationSummary = $notificationSummary ?? [];
    $notificationFeed = collect($notificationFeed ?? []);
    $onuColor = match ($onu['color'] ?? 'gray') {
        'success' => 'emerald',
        'warning' => 'amber',
        'danger' => 'rose',
        default => 'slate',
    };
    $isOnline = (bool) ($conn['online'] ?? false);
@endphp

@section('content')
    <div
        id="dashboard-live-panel"
        data-live-url="{{ route('portal.dashboard.live') }}"
        data-poll-ms="{{ (int) config('portal.poll_seconds', 5) * 1000 }}"
        data-dash='@json($dash)'>
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

    <div class="portal-kpi-grid portal-kpi-grid--4">
        <article class="portal-summary-card {{ ($notificationSummary['action_required'] ?? 0) > 0 ? 'portal-summary-card--due' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Action required</p>
            <p class="portal-summary-card__value">{{ $notificationSummary['action_required'] ?? 0 }}</p>
            <p class="portal-summary-card__meta">Bills, outages, signal, or expiry alerts that need attention.</p>
        </article>
        <article class="portal-summary-card {{ ! empty($bill['has_due']) ? 'portal-summary-card--warn' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Current balance</p>
            <p class="portal-summary-card__value">{{ number_format($bill['total_due'] ?? 0, 2) }} BDT</p>
            <p class="portal-summary-card__meta">{{ ! empty($bill['has_due']) ? ($bill['next_invoice_label'] ?? 'Payment pending') : 'No unpaid invoice right now.' }}</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Monthly transfer</p>
            <p class="portal-summary-card__value">{{ \App\Models\BandwidthUsageDaily::formatBytes(($traffic['month_download'] ?? 0) + ($traffic['month_upload'] ?? 0)) }}</p>
            <p class="portal-summary-card__meta">Down {{ \App\Models\BandwidthUsageDaily::formatBytes($traffic['month_download'] ?? 0) }} · Up {{ \App\Models\BandwidthUsageDaily::formatBytes($traffic['month_upload'] ?? 0) }}</p>
        </article>
        <article class="portal-summary-card {{ $pkg && ! empty($pkg['expires_at']) ? 'portal-summary-card--info' : 'portal-summary-card--warn' }}">
            <p class="portal-summary-card__eyebrow">Package</p>
            <p class="portal-summary-card__value">{{ $pkg['name'] ?? 'Not assigned' }}</p>
            <p class="portal-summary-card__meta">
                @if ($pkg)
                    {{ $pkg['download_mbps'] }} Mbps @if(! empty($pkg['upload_mbps'])) / {{ $pkg['upload_mbps'] }} Mbps @endif · Expires {{ $pkg['expires_at'] ?? '—' }}
                @else
                    Contact support if your active package is missing.
                @endif
            </p>
        </article>
    </div>

    @if (($movieServers ?? collect())->isNotEmpty())
        <div style="margin-top: 1.25rem;">
            <x-movie-servers-showcase :servers="$movieServers" variant="portal" />
        </div>
    @endif

    @if ($outages->isNotEmpty())
        <div class="portal-panel portal-panel--warning">
            <p class="portal-panel__title">Area notices</p>
            <ul class="portal-list-compact portal-pro-card__meta">
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
            <p class="portal-pro-card__meta">Connected since: <span id="stat-started">{{ $conn['session_started'] ?? '—' }}</span></p>
            <p class="portal-pro-card__meta">Uptime: <span id="stat-uptime">{{ $conn['session_uptime'] ?? $conn['connection_duration'] ?? '—' }}</span></p>
            <p class="portal-pro-card__meta">Last disconnect: <span id="stat-last">{{ $conn['last_disconnect'] ?? $conn['last_online'] ?? '—' }}</span></p>
            @if (! empty($conn['portal_last_logout_at']) && ($conn['portal_last_logout_at'] ?? '—') !== '—')
                <p class="portal-pro-card__meta">App logout: <span id="stat-portal-logout">{{ $conn['portal_last_logout_at'] }}</span></p>
            @endif
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
            @if (! empty($bill['has_due']))
                @if (! empty($bill['next_invoice_label']))
                    <p class="portal-pro-card__meta">{{ $bill['next_invoice_label'] }}</p>
                @endif
                <a href="{{ route('portal.bills.index') }}" class="portal-btn-primary portal-pro-card__link" style="color: #fff; margin-top: 0.85rem;">{{ $bill['cta_label'] ?? 'Pay bill' }}</a>
            @else
                <p class="portal-pro-card__meta" style="color: #059669; font-weight: 700;">No unpaid invoice right now.</p>
                <a href="{{ route('portal.bills.index') }}" class="portal-pro-card__link" style="margin-top: 0.85rem;">{{ $bill['cta_label'] ?? 'View bills' }} →</a>
            @endif
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
                <a href="{{ route('portal.speed-test.index') }}" class="portal-pro-chip portal-pro-chip--indigo">Speed test</a>
                <a href="{{ route('portal.tickets.create') }}" class="portal-pro-chip portal-pro-chip--amber">Support</a>
                <a href="{{ route('portal.notifications.index') }}" class="portal-pro-chip portal-pro-chip--violet">
                    Alerts @if(($dash['notifications_count'] ?? 0) > 0)({{ $dash['notifications_count'] }})@endif
                </a>
                @if (config('portal.whatsapp_url'))
                    <a href="{{ config('portal.whatsapp_url') }}" target="_blank" rel="noopener" class="portal-pro-chip portal-pro-chip--emerald">WhatsApp</a>
                @endif
            </div>
        </article>
    </div>

    <div class="portal-panel">
        <h2 class="portal-panel__title">Live bandwidth (12h)</h2>
        <canvas id="dash-chart" style="margin-top: 0.85rem; width: 100%; height: 10rem;" height="140"></canvas>
    </div>

    <div class="portal-panel">
        <div class="portal-panel__head">
            <h2 class="portal-panel__title">Recent alerts</h2>
            <a href="{{ route('portal.notifications.index') }}" class="portal-pro-card__link" style="margin: 0;">Open alerts →</a>
        </div>
        <div class="portal-alert-feed">
            @forelse ($notificationFeed as $item)
                <article class="portal-alert-card portal-alert-card--{{ $item['severity'] }}">
                    <div class="portal-alert-card__head">
                        <div>
                            <h3 class="portal-alert-card__title">{{ $item['title'] }}</h3>
                            <p class="portal-alert-card__body">{{ $item['message'] }}</p>
                        </div>
                        <span class="portal-alert-card__time">{{ \Carbon\Carbon::parse($item['at'])->diffForHumans() }}</span>
                    </div>
                    <div class="portal-alert-card__meta">
                        <span class="portal-status-pill portal-status-pill--{{ $item['severity'] === 'danger' ? 'danger' : ($item['severity'] === 'warning' ? 'warning' : ($item['severity'] === 'success' ? 'success' : 'muted')) }}">
                            {{ \Illuminate\Support\Str::headline($item['type']) }}
                        </span>
                    </div>
                </article>
            @empty
                <p class="portal-empty-state">No alerts right now. Billing, outage, payment, and optical updates will appear here.</p>
            @endforelse
        </div>
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
                        @php
                            $due = round((float) $inv->total - (float) $inv->amount_paid, 2);
                            $dueClass = $due > 0 ? 'portal-amount-due' : 'portal-amount-ok';
                        @endphp
                        <tr>
                            <td><a href="{{ route('portal.invoices.show', $inv) }}" class="portal-link">{{ $inv->invoice_number }}</a></td>
                            <td class="{{ $dueClass }}" style="text-align: right;">{{ number_format($due, 2) }}</td>
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
            const dashboardPanel = document.getElementById('dashboard-live-panel');
            const pollMs = Number(dashboardPanel.dataset.pollMs || 5000);
            const liveUrl = dashboardPanel.dataset.liveUrl;
            let dash = JSON.parse(dashboardPanel.dataset.dash || '{}');
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
                    const res = await fetch(liveUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) return;
                    dash = await res.json();
                    const c = dash.connection, t = dash.traffic, o = dash.onu, b = dash.billing;
                    setOnline(c.online);
                    document.getElementById('stat-ppp').textContent = c.router_status || '—';
                    document.getElementById('stat-ip').textContent = c.framed_ip || '—';
                    const startedEl = document.getElementById('stat-started');
                    if (startedEl) startedEl.textContent = c.session_started || c.session_started_formatted || '—';
                    document.getElementById('stat-uptime').textContent = c.session_uptime || c.connection_duration || '—';
                    document.getElementById('stat-last').textContent = c.last_disconnect || c.last_online || '—';
                    const logoutEl = document.getElementById('stat-portal-logout');
                    if (logoutEl) logoutEl.textContent = c.portal_last_logout_at || '—';
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
    </div>
@endsection
