@extends('portal.layout')

@section('title', 'Speed test')

@section('content')
    <div
        id="speed-test-panel"
        data-ping-url="{{ route('portal.speed-test.ping') }}"
        data-download-url="{{ route('portal.speed-test.download') }}"
        data-upload-url="{{ route('portal.speed-test.upload') }}"
        data-upload-bytes="{{ (int) config('portal.speed_test.upload_bytes', 262_144) }}">
        <div class="portal-page-head portal-page-head--stack">
            <div>
                <h1 class="portal-page-title">Internet speed test</h1>
                <p class="portal-page-lead">Measure ping, download, and upload against your ISP portal test server.</p>
            </div>
            <a href="{{ route('portal.usage.index') }}" class="portal-card-button">Live usage</a>
        </div>

        <section class="portal-usage-quick portal-speed-hero">
            <div class="portal-usage-quick__hero">
                <div id="st-ring" class="portal-usage-quick__ring portal-speed-hero__ring" aria-hidden="true">
                    <span class="portal-usage-quick__ring-label">Speed test</span>
                    <span id="st-ring-value" class="portal-usage-quick__ring-value">—</span>
                    <span class="portal-usage-quick__ring-unit">Tap run below</span>
                </div>
                <div class="portal-usage-quick__side">
                    <div class="portal-usage-quick__metric">
                        <p class="portal-usage-quick__metric-label">Ping</p>
                        <p class="portal-usage-quick__metric-value"><span id="st-ping">—</span> <span class="portal-usage-quick__metric-unit">ms</span></p>
                    </div>
                    <div class="portal-usage-quick__metric">
                        <p class="portal-usage-quick__metric-label">Download</p>
                        <p class="portal-usage-quick__metric-value"><span id="st-down">—</span> <span class="portal-usage-quick__metric-unit">Mbps</span></p>
                    </div>
                    <div class="portal-usage-quick__metric">
                        <p class="portal-usage-quick__metric-label">Upload</p>
                        <p class="portal-usage-quick__metric-value"><span id="st-up">—</span> <span class="portal-usage-quick__metric-unit">Mbps</span></p>
                    </div>
                </div>
            </div>
            <div class="portal-usage-quick__actions">
                <button type="button" id="st-run" class="portal-btn-primary portal-usage-quick__btn">Run speed test</button>
                <p id="st-status" class="portal-usage-quick__status">Ready to start.</p>
            </div>
        </section>

        <div class="portal-summary-grid">
            <article class="portal-summary-card portal-summary-card--info">
                <p class="portal-summary-card__eyebrow">Best practice</p>
                <p class="portal-summary-card__value">Single device</p>
                <p class="portal-summary-card__meta">Pause large downloads, streaming, and cloud sync for a cleaner result.</p>
            </article>
            <article class="portal-summary-card portal-summary-card--warn">
                <p class="portal-summary-card__eyebrow">For accuracy</p>
                <p class="portal-summary-card__value">Prefer LAN cable</p>
                <p class="portal-summary-card__meta">Wi-Fi speed varies by distance, walls, and router quality.</p>
            </article>
        </div>

        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Test stages</h2>
                    <p class="portal-surface-card__meta">Ping first, then download (~1 MB), then upload (~256 KB).</p>
                </div>
            </div>

            <div class="portal-test-stage">
                <span id="stage-ping" class="portal-test-stage__item">1. Ping</span>
                <span id="stage-down" class="portal-test-stage__item">2. Download</span>
                <span id="stage-up" class="portal-test-stage__item">3. Upload</span>
            </div>
        </section>
    </div>

    @push('scripts')
        <script src="{{ asset('js/portal-speed-test.js') }}?v=2" defer></script>
    @endpush
@endsection
