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
        class="portal-chart-shell portal-usage-chart"
        data-live-url="{{ route('portal.usage.live') }}"
        data-quick-url="{{ route('portal.speed-test.quick') }}"
        data-ping-url="{{ route('portal.speed-test.ping') }}"
        data-poll-ms="{{ $pollSeconds * 1000 }}"
        data-auto-quick="0"
        data-stats='@json($stats)'>
        <div class="portal-section-head portal-section-head--compact">
            <div class="portal-label-stack">
                <h2 class="portal-surface-card__title">Speed trend</h2>
                <p class="portal-surface-card__meta">Recent download & upload (Mbps)</p>
            </div>
        </div>
        <div class="portal-usage-chart__canvas-wrap">
            <canvas id="usage-chart" height="200"></canvas>
        </div>
    </section>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
        <script src="{{ asset('js/portal-usage.js') }}?v=1" defer></script>
    @endpush
@endsection
