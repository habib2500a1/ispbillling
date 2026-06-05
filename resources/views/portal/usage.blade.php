@extends('portal.layout')

@section('title', 'Usage & speed test')

@section('content')
    <div class="portal-speedtest-page portal-usage-page">
        <div class="portal-page-head portal-page-head--stack">
            <div>
                <h1 class="portal-page-title">Usage &amp; speed test</h1>
                <p class="portal-page-lead">
                    Tap <strong>START</strong> for download, upload, and latency — then see live usage from your connection below.
                </p>
            </div>
            <p id="usage-updated" class="portal-live-badge">Updating…</p>
        </div>

        <section class="portal-usage-speedtest-wrap" aria-label="Internet speed test">
            @include('portal.partials.speed-test-widget', [
                'speedtest' => $speedtest ?? [
                    'ping_url' => (string) config('portal.speed_test.external.ping_url'),
                    'download_url' => (string) config('portal.speed_test.external.download_url'),
                    'upload_url' => (string) config('portal.speed_test.external.upload_url'),
                ],
            ])
        </section>

        <header class="portal-usage-section-head">
            <h2 class="portal-usage-section-head__title">Live connection</h2>
            <p class="portal-usage-section-head__lead">Router sync — refreshes every {{ $pollSeconds }}s</p>
        </header>

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
                Speed test measures internet path to the test server. Live stats below are from {{ $companyName ?? config('app.name') }} router sync.
            </p>
        </details>

        <section
            id="usage-panel"
            class="portal-live-graph"
            data-live-url="{{ route('portal.usage.live') }}"
            data-poll-ms="{{ $pollSeconds * 1000 }}"
            data-stats='@json($stats)'>
            <header class="portal-live-graph__head">
                <div class="portal-live-graph__title-row">
                    <span class="portal-live-graph__icon" aria-hidden="true">📈</span>
                    <h2 class="portal-live-graph__title">Live usage graph</h2>
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
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
        <script src="{{ asset('js/portal-usage.js') }}?v=6" defer></script>
        <script src="{{ asset('js/portal-speedtest-live.js') }}?v={{ @filemtime(public_path('js/portal-speedtest-live.js')) ?: 1 }}" defer></script>
    @endpush
@endsection
