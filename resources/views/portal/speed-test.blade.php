@extends('portal.layout')

@section('title', 'Speed test')

@section('content')
    <h1 class="text-2xl font-bold text-slate-900">Internet speed test</h1>
    <p class="mt-1 text-sm text-slate-600">Ping, download, and upload to our server.</p>

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="portal-metric-card border-violet-200 text-center">
            <p class="text-xs font-bold uppercase text-slate-500">Ping</p>
            <p id="st-ping" class="mt-2 text-3xl font-bold text-violet-700">—</p>
            <p class="text-xs text-slate-500">ms</p>
        </div>
        <div class="portal-metric-card border-amber-200 text-center">
            <p class="text-xs font-bold uppercase text-slate-500">Download</p>
            <p id="st-down" class="mt-2 text-3xl font-bold text-amber-800">—</p>
            <p class="text-xs text-slate-500">Mbps</p>
        </div>
        <div class="portal-metric-card border-sky-200 text-center">
            <p class="text-xs font-bold uppercase text-slate-500">Upload</p>
            <p id="st-up" class="mt-2 text-3xl font-bold text-sky-800">—</p>
            <p class="text-xs text-slate-500">Mbps</p>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap gap-3">
        <button type="button" id="st-run" class="portal-btn-primary">Run speed test</button>
        <p id="st-status" class="self-center text-sm text-slate-600"></p>
    </div>

    @push('scripts')
        <script>
            const pingUrl = @json(route('portal.speed-test.ping'));
            const downUrl = @json(route('portal.speed-test.download'));
            const upUrl = @json(route('portal.speed-test.upload'));
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

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
                status.textContent = 'Testing ping…';
                try {
                    const ping = await measurePing();
                    document.getElementById('st-ping').textContent = ping.toFixed(0);
                    status.textContent = 'Testing download…';
                    const down = await measureDownload();
                    document.getElementById('st-down').textContent = down.toFixed(2);
                    status.textContent = 'Testing upload…';
                    const up = await measureUpload();
                    document.getElementById('st-up').textContent = up.toFixed(2);
                    status.textContent = 'Done at ' + new Date().toLocaleTimeString();
                } catch (e) {
                    status.textContent = 'Test failed. Try again.';
                }
                btn.disabled = false;
            });
        </script>
    @endpush
@endsection
