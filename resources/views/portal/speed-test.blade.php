@extends('portal.layout')

@section('title', 'Speed test')

@section('content')
    <div
        id="speed-test-panel"
        data-ping-url="{{ route('portal.speed-test.ping') }}"
        data-download-url="{{ route('portal.speed-test.download') }}"
        data-upload-url="{{ route('portal.speed-test.upload') }}">
        <div class="portal-page-head">
            <div>
                <h1 class="portal-page-title">Internet speed test</h1>
                <p class="portal-page-lead">Measure latency, download, and upload speed against the ISP portal test server.</p>
            </div>
            <a href="{{ route('portal.usage.index') }}" class="portal-card-button">Open live usage</a>
        </div>

        <div class="portal-summary-grid">
            <article class="portal-summary-card portal-summary-card--info">
                <p class="portal-summary-card__eyebrow">Best practice</p>
                <p class="portal-summary-card__value">Single device</p>
                <p class="portal-summary-card__meta">Pause large downloads, streaming, and cloud sync to get a cleaner result.</p>
            </article>
            <article class="portal-summary-card portal-summary-card--warn">
                <p class="portal-summary-card__eyebrow">For accuracy</p>
                <p class="portal-summary-card__value">Use Wi-Fi carefully</p>
                <p class="portal-summary-card__meta">LAN cable gives the most accurate speed reading; Wi-Fi may vary by distance and router quality.</p>
            </article>
        </div>

        <div class="portal-speed-grid">
            <div class="portal-speed-card">
                <p class="portal-speed-card__label">Ping</p>
                <p id="st-ping" class="portal-speed-card__value">-</p>
                <p class="portal-speed-card__unit">ms</p>
            </div>
            <div class="portal-speed-card">
                <p class="portal-speed-card__label">Download</p>
                <p id="st-down" class="portal-speed-card__value">-</p>
                <p class="portal-speed-card__unit">Mbps</p>
            </div>
            <div class="portal-speed-card">
                <p class="portal-speed-card__label">Upload</p>
                <p id="st-up" class="portal-speed-card__value">-</p>
                <p class="portal-speed-card__unit">Mbps</p>
            </div>
        </div>

        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Run test</h2>
                    <p class="portal-surface-card__meta">The test measures ping first, then download, then upload using your browser.</p>
                </div>
            </div>

            <div class="portal-test-stage">
                <span id="stage-ping" class="portal-test-stage__item">1. Ping</span>
                <span id="stage-down" class="portal-test-stage__item">2. Download</span>
                <span id="stage-up" class="portal-test-stage__item">3. Upload</span>
            </div>

            <div class="portal-form-actions">
                <button type="button" id="st-run" class="portal-btn-primary">Run speed test</button>
                <p id="st-status" class="portal-surface-card__meta">Ready to start.</p>
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            const speedTestPanel = document.getElementById('speed-test-panel');
            const pingUrl = speedTestPanel.dataset.pingUrl;
            const downUrl = speedTestPanel.dataset.downloadUrl;
            const upUrl = speedTestPanel.dataset.uploadUrl;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const stages = {
                ping: document.getElementById('stage-ping'),
                down: document.getElementById('stage-down'),
                up: document.getElementById('stage-up'),
            };

            function resetStages() {
                Object.values(stages).forEach((el) => {
                    el.className = 'portal-test-stage__item';
                });
            }

            function setStage(name, state) {
                stages[name].className = 'portal-test-stage__item' + (state === 'active' ? ' is-active' : state === 'done' ? ' is-done' : '');
            }

            async function measurePing(samples = 5) {
                const times = [];
                for (let i = 0; i < samples; i++) {
                    const t0 = performance.now();
                    await fetch(pingUrl + '?_=' + Date.now(), { cache: 'no-store' });
                    times.push(performance.now() - t0);
                }
                times.sort((a, b) => a - b);
                return times[Math.floor(times.length / 2)];
            }

            async function measureDownload() {
                const t0 = performance.now();
                const res = await fetch(downUrl + '?_=' + Date.now(), { cache: 'no-store' });
                const blob = await res.blob();
                const sec = (performance.now() - t0) / 1000;
                const mbps = (blob.size * 8) / sec / 1_000_000;
                return mbps;
            }

            async function measureUpload() {
                const payload = new Uint8Array(512 * 1024);
                crypto.getRandomValues(payload);
                const t0 = performance.now();
                await fetch(upUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/octet-stream' },
                    body: payload,
                });
                const sec = (performance.now() - t0) / 1000;
                return (payload.length * 8) / sec / 1_000_000;
            }

            document.getElementById('st-run').addEventListener('click', async () => {
                const btn = document.getElementById('st-run');
                const status = document.getElementById('st-status');
                btn.disabled = true;
                resetStages();
                setStage('ping', 'active');
                status.textContent = 'Testing ping...';
                try {
                    const ping = await measurePing();
                    document.getElementById('st-ping').textContent = ping.toFixed(0);
                    setStage('ping', 'done');
                    setStage('down', 'active');
                    status.textContent = 'Testing download...';
                    const down = await measureDownload();
                    document.getElementById('st-down').textContent = down.toFixed(2);
                    setStage('down', 'done');
                    setStage('up', 'active');
                    status.textContent = 'Testing upload...';
                    const up = await measureUpload();
                    document.getElementById('st-up').textContent = up.toFixed(2);
                    setStage('up', 'done');
                    status.textContent = 'Done at ' + new Date().toLocaleTimeString();
                } catch (e) {
                    status.textContent = 'Test failed. Try again.';
                    resetStages();
                }
                btn.disabled = false;
            });
        </script>
    @endpush
@endsection
