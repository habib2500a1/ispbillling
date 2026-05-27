@extends('portal.layout')

@section('title', 'Live usage')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Live internet usage</h1>
            <p class="portal-page-lead">Monitor connection state, current throughput, and session totals from your customer portal.</p>
        </div>
        <p id="usage-updated" class="portal-live-badge">Updating...</p>
    </div>

    <div class="portal-summary-grid portal-summary-grid--wide">
        <article id="status-card" class="portal-summary-card {{ ($stats['online'] ?? false) ? 'portal-summary-card--ok' : 'portal-summary-card--warn' }}">
            <p class="portal-summary-card__eyebrow">Connection status</p>
            <p id="stat-online" class="portal-summary-card__value">{{ ($stats['online'] ?? false) ? 'Online' : 'Offline' }}</p>
            <p class="portal-summary-card__meta">
                <span id="stat-online-pill" class="portal-status-pill {{ ($stats['online'] ?? false) ? 'portal-status-pill--success' : 'portal-status-pill--muted' }}">
                    {{ ($stats['online'] ?? false) ? 'Session active' : 'No live session' }}
                </span>
            </p>
        </article>
        <article class="portal-summary-card portal-summary-card--warn">
            <p class="portal-summary-card__eyebrow">Live download</p>
            <p id="stat-download" class="portal-summary-card__value">{{ \App\Support\BandwidthDirection::formatBps($stats['download_bps'] ?? null) }}</p>
            <p class="portal-summary-card__meta">Current downstream throughput from your ISP connection.</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Live upload</p>
            <p id="stat-upload" class="portal-summary-card__value">{{ \App\Support\BandwidthDirection::formatBps($stats['upload_bps'] ?? null) }}</p>
            <p class="portal-summary-card__meta">Current upstream usage based on the latest router sync.</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Today total</p>
            <p id="stat-today" class="portal-summary-card__value text-base sm:text-lg">
                ↓ {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['today_download'] ?? 0) }}
                · ↑ {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['today_upload'] ?? 0) }}
            </p>
            <p class="portal-summary-card__meta">Accumulated transfer for today across all synced samples.</p>
        </article>
    </div>

    <div class="portal-section-grid portal-section-grid--2">
        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Session details</h2>
                    <p class="portal-surface-card__meta">These values reflect the most recent live accounting data available from your router sync.</p>
                </div>
            </div>

            <dl class="portal-detail-list">
                <div class="portal-detail-list__item">
                    <dt>IP address</dt>
                    <dd id="stat-ip" class="portal-mono">{{ $stats['framed_ip'] ?? '-' }}</dd>
                </div>
                <div class="portal-detail-list__item">
                    <dt>Session download</dt>
                    <dd id="stat-session-down">{{ \App\Models\BandwidthUsageDaily::formatBytes($stats['total_download'] ?? 0) }}</dd>
                </div>
                <div class="portal-detail-list__item">
                    <dt>Session upload</dt>
                    <dd id="stat-session-up">{{ \App\Models\BandwidthUsageDaily::formatBytes($stats['total_upload'] ?? 0) }}</dd>
                </div>
            </dl>
        </section>

        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Refresh notes</h2>
                    <p class="portal-surface-card__meta">Live speed updates are frequent, but router accounting can arrive a bit later.</p>
                </div>
            </div>

            <div class="portal-note-banner">
                Speed updates every 30 seconds. If download or upload shows "-", keep browsing for 1 to 2 minutes and refresh again after the router sync catches up.
            </div>
        </section>
    </div>

    <section
        id="usage-panel"
        class="portal-chart-shell"
        data-live-url="{{ route('portal.usage.live') }}"
        data-stats='@json($stats)'>
        <div class="portal-section-head">
            <div class="portal-label-stack">
                <h2 class="portal-surface-card__title">Last 12 hours</h2>
                <p class="portal-surface-card__meta">Average Mbps trend for download and upload across recent usage snapshots.</p>
            </div>
        </div>
        <canvas id="usage-chart" class="mt-4 h-48 w-full" height="160"></canvas>
    </section>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            const usagePanel = document.getElementById('usage-panel');
            const initial = JSON.parse(usagePanel.dataset.stats || '{}');
            const liveUrl = usagePanel.dataset.liveUrl;
            const ctx = document.getElementById('usage-chart');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: initial.chart.labels,
                    datasets: [
                        { label: 'Download', data: initial.chart.download_mbps, borderColor: '#d97706', tension: 0.3, fill: false },
                        { label: 'Upload', data: initial.chart.upload_mbps, borderColor: '#0284c7', tension: 0.3, fill: false },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true },
                    },
                },
            });

            function formatBps(bps) {
                if (bps === null || bps === undefined) return '-';
                if (bps <= 0) return '0 bps';
                if (bps >= 1000000) return (bps / 1000000).toFixed(2) + ' Mbps';
                if (bps >= 1000) return (bps / 1000).toFixed(1) + ' Kbps';
                return bps + ' bps';
            }

            function formatBytes(bytes) {
                if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
                if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
                if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return bytes + ' B';
            }

            function setOnlineState(online) {
                const statusCard = document.getElementById('status-card');
                const statusValue = document.getElementById('stat-online');
                const statusPill = document.getElementById('stat-online-pill');

                statusCard.className = 'portal-summary-card ' + (online ? 'portal-summary-card--ok' : 'portal-summary-card--warn');
                statusValue.textContent = online ? 'Online' : 'Offline';
                statusPill.className = 'portal-status-pill ' + (online ? 'portal-status-pill--success' : 'portal-status-pill--muted');
                statusPill.textContent = online ? 'Session active' : 'No live session';
            }

            async function refreshLive() {
                try {
                    const res = await fetch(liveUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    setOnlineState(data.online);
                    document.getElementById('stat-download').textContent = formatBps(data.download_bps);
                    document.getElementById('stat-upload').textContent = formatBps(data.upload_bps);
                    document.getElementById('stat-today').textContent = '↓ ' + formatBytes(data.today_download) + ' · ↑ ' + formatBytes(data.today_upload);
                    document.getElementById('stat-ip').textContent = data.framed_ip || '-';
                    document.getElementById('stat-session-down').textContent = formatBytes(data.total_download);
                    document.getElementById('stat-session-up').textContent = formatBytes(data.total_upload);
                    chart.data.labels = data.chart.labels;
                    chart.data.datasets[0].data = data.chart.download_mbps;
                    chart.data.datasets[1].data = data.chart.upload_mbps;
                    chart.update();
                    document.getElementById('usage-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
                } catch (e) {
                    document.getElementById('usage-updated').textContent = 'Could not refresh';
                }
            }

            document.getElementById('usage-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
            setOnlineState(Boolean(initial.online));
            setInterval(refreshLive, 30000);
        </script>
    @endpush
@endsection
