{{-- Live router usage summary (shared by Usage + Speed test pages). --}}
@php
    $stats = $stats ?? [];
    $pollSeconds = $pollSeconds ?? max(1, (int) config('portal.poll_seconds', 1));
@endphp

<section class="portal-summary-grid portal-summary-grid--usage portal-speedtest-usage" aria-label="Live usage">
    <article id="status-card" class="portal-summary-card portal-usage-stat {{ ($stats['online'] ?? false) ? 'portal-summary-card--ok' : 'portal-summary-card--warn' }}">
        <p class="portal-summary-card__eyebrow">Status</p>
        <p id="stat-online" class="portal-summary-card__value">{{ ($stats['online'] ?? false) ? 'Online' : 'Offline' }}</p>
        <p class="portal-summary-card__meta">
            <span id="stat-online-pill" class="portal-status-pill {{ ($stats['online'] ?? false) ? 'portal-status-pill--success' : 'portal-status-pill--muted' }}">
                {{ ($stats['online'] ?? false) ? 'Active' : 'No session' }}
            </span>
        </p>
    </article>
    <article class="portal-summary-card portal-usage-stat portal-summary-card--warn">
        <p class="portal-summary-card__eyebrow">Live download</p>
        <p id="stat-download" class="portal-summary-card__value portal-usage-speed-value">{{ \App\Support\BandwidthDirection::formatBps($stats['download_bps'] ?? null) }}</p>
        <p class="portal-summary-card__meta">Router sync</p>
    </article>
    <article class="portal-summary-card portal-usage-stat portal-summary-card--info">
        <p class="portal-summary-card__eyebrow">Live upload</p>
        <p id="stat-upload" class="portal-summary-card__value portal-usage-speed-value">{{ \App\Support\BandwidthDirection::formatBps($stats['upload_bps'] ?? null) }}</p>
        <p class="portal-summary-card__meta">Router sync</p>
    </article>
    <article class="portal-summary-card portal-usage-stat portal-summary-card--info">
        <p class="portal-summary-card__eyebrow">Today</p>
        <p id="stat-today" class="portal-summary-card__value portal-summary-card__value--compact">
            ↓ {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['today_download'] ?? 0) }}
            · ↑ {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['today_upload'] ?? 0) }}
        </p>
        <p class="portal-summary-card__meta">Total transfer today</p>
    </article>
</section>

<section
    id="usage-panel"
    class="portal-live-graph portal-live-graph--compact"
    data-live-url="{{ route('portal.usage.live') }}"
    data-quick-url="{{ route('portal.speed-test.quick') }}"
    data-ping-url="{{ route('portal.speed-test.ping') }}"
    data-poll-ms="{{ $pollSeconds * 1000 }}"
    data-auto-quick="0"
    data-stats='@json($stats)'>
    <header class="portal-live-graph__head">
        <div class="portal-live-graph__title-row">
            <span class="portal-live-graph__icon" aria-hidden="true">📈</span>
            <h2 class="portal-live-graph__title">Live usage</h2>
            <span id="portal-live-badge" class="portal-live-graph__badge {{ ($stats['online'] ?? false) ? 'is-live' : 'is-off' }}">
                {{ ($stats['online'] ?? false) ? 'LIVE' : 'OFFLINE' }}
            </span>
        </div>
        <div class="portal-live-graph__pills" aria-live="polite">
            <span class="portal-live-graph__pill portal-live-graph__pill--down">
                <span id="portal-live-down-mbps" class="portal-live-graph__pill-value">{{ number_format($stats['download_mbps'] ?? 0, 2) }}</span>
                <span aria-hidden="true">↓</span> Mbps
            </span>
            <span class="portal-live-graph__pill portal-live-graph__pill--up">
                <span id="portal-live-up-mbps" class="portal-live-graph__pill-value">{{ number_format($stats['upload_mbps'] ?? 0, 2) }}</span>
                <span aria-hidden="true">↑</span> Mbps
            </span>
        </div>
    </header>
    <div class="portal-live-graph__canvas-wrap">
        <canvas id="usage-chart" height="140"></canvas>
    </div>
</section>

<p id="usage-updated" class="portal-live-badge portal-speedtest-usage__updated">Updating…</p>
