@extends('portal.layout')

@section('title', 'Live usage')

@section('content')
    <div class="portal-page-head portal-page-head--stack">
        <div>
            <h1 class="portal-page-title">Usage & speed</h1>
            <p class="portal-page-lead">Quick speed check in about 1 second, plus live download/upload from your connection.</p>
        </div>
        <p id="usage-updated" class="portal-live-badge">Updating…</p>
    </div>

    {{-- Quick 1-second speed test (mobile-first) --}}
    <section
        id="usage-quick-panel"
        class="portal-usage-quick"
        aria-label="Quick speed test">
        <div class="portal-usage-quick__hero">
            <div id="usage-quick-ring" class="portal-usage-quick__ring" aria-hidden="true">
                <span class="portal-usage-quick__ring-label">Download</span>
                <span id="usage-quick-down" class="portal-usage-quick__ring-value">—</span>
                <span class="portal-usage-quick__ring-unit">Mbps</span>
            </div>
            <div class="portal-usage-quick__side">
                <div class="portal-usage-quick__metric">
                    <span class="portal-usage-quick__metric-label">Ping</span>
                    <strong id="usage-quick-ping" class="portal-usage-quick__metric-value">—</strong>
                    <span class="portal-usage-quick__metric-unit">ms</span>
                </div>
                <p id="usage-quick-status" class="portal-usage-quick__status">Tap below for a ~1 second speed check.</p>
                <button type="button" id="usage-quick-run" class="portal-btn-primary portal-usage-quick__btn">
                    Check speed now
                </button>
                <a href="{{ route('portal.speed-test.index') }}" class="portal-card-button portal-usage-quick__link">Full speed test →</a>
            </div>
        </div>
    </section>

    {{-- Live router stats --}}
    <div class="portal-summary-grid portal-summary-grid--usage">
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
            <p class="portal-summary-card__meta">From router sync</p>
        </article>
        <article class="portal-summary-card portal-usage-stat portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Live upload</p>
            <p id="stat-upload" class="portal-summary-card__value portal-usage-speed-value">{{ \App\Support\BandwidthDirection::formatBps($stats['upload_bps'] ?? null) }}</p>
            <p class="portal-summary-card__meta">From router sync</p>
        </article>
        <article class="portal-summary-card portal-usage-stat portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Today</p>
            <p id="stat-today" class="portal-summary-card__value portal-summary-card__value--compact">
                ↓ {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['today_download'] ?? 0) }}
                · ↑ {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['today_upload'] ?? 0) }}
            </p>
            <p class="portal-summary-card__meta">Total transfer today</p>
        </article>
    </div>

    <details class="portal-usage-details portal-surface-card">
        <summary class="portal-usage-details__summary">Session details</summary>
        <dl class="portal-detail-list portal-detail-list--mobile">
            <div class="portal-detail-list__item">
                <dt>IP address</dt>
                <dd id="stat-ip" class="portal-mono">{{ $stats['framed_ip'] ?? '—' }}</dd>
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
        <p class="portal-note-banner portal-note-banner--compact">
            Live speed refreshes every {{ $pollSeconds }}s. Router “live” may show “—” for 1–2 min after connecting.
        </p>
    </details>

    <section
        id="usage-panel"
        class="portal-live-graph"
        data-live-url="{{ route('portal.usage.live') }}"
        data-quick-url="{{ route('portal.speed-test.quick') }}"
        data-ping-url="{{ route('portal.speed-test.ping') }}"
        data-poll-ms="{{ $pollSeconds * 1000 }}"
        data-auto-quick="0"
        data-stats='@json($stats)'>
        <header class="portal-live-graph__head">
            <div class="portal-live-graph__title-row">
                <span class="portal-live-graph__icon" aria-hidden="true">📈</span>
                <h2 class="portal-live-graph__title">Live Usage Graph</h2>
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
            <canvas id="usage-chart" height="220"></canvas>
        </div>
        <footer class="portal-live-graph__stats" aria-label="Usage statistics">
            <div class="portal-live-graph__stat">
                <span class="portal-live-graph__stat-label">Session ↓</span>
                <strong id="portal-stat-session-down" class="portal-live-graph__stat-value portal-live-graph__stat-value--down">
                    {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['total_download'] ?? 0) }}
                </strong>
            </div>
            <div class="portal-live-graph__stat">
                <span class="portal-live-graph__stat-label">Session ↑</span>
                <strong id="portal-stat-session-up" class="portal-live-graph__stat-value portal-live-graph__stat-value--up">
                    {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['total_upload'] ?? 0) }}
                </strong>
            </div>
            <div class="portal-live-graph__stat">
                <span class="portal-live-graph__stat-label">Session total</span>
                <strong id="portal-stat-session-total" class="portal-live-graph__stat-value">
                    {{ \App\Models\BandwidthUsageDaily::formatBytes(($stats['total_download'] ?? 0) + ($stats['total_upload'] ?? 0)) }}
                </strong>
            </div>
            <div class="portal-live-graph__stat">
                <span class="portal-live-graph__stat-label">Today total</span>
                <strong id="portal-stat-today-total" class="portal-live-graph__stat-value portal-live-graph__stat-value--today">
                    {{ \App\Models\BandwidthUsageDaily::formatBytes(($stats['today_download'] ?? 0) + ($stats['today_upload'] ?? 0)) }}
                </strong>
            </div>
            <div class="portal-live-graph__stat">
                <span class="portal-live-graph__stat-label">Uptime</span>
                <strong id="portal-stat-uptime" class="portal-live-graph__stat-value portal-live-graph__stat-value--mono">{{ $stats['uptime'] ?? '0:00:00' }}</strong>
            </div>
        </footer>
    </section>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
        <script src="{{ asset('js/portal-usage.js') }}?v=3" defer></script>
    @endpush
@endsection
