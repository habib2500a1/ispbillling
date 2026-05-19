@extends('portal.layout')

@section('title', 'Live usage')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Live internet usage</h1>
            <p class="mt-1 text-sm text-slate-600">Real-time download/upload for your connection.</p>
        </div>
        <p id="usage-updated" class="text-xs text-slate-500">Updating…</p>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div id="card-status" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
            <p id="stat-online" class="mt-2 text-xl font-bold {{ ($stats['online'] ?? false) ? 'text-emerald-600' : 'text-slate-500' }}">
                {{ ($stats['online'] ?? false) ? 'Online' : 'Offline' }}
            </p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Live download</p>
            <p id="stat-download" class="mt-2 text-2xl font-bold text-amber-950">{{ \App\Support\BandwidthDirection::formatBps($stats['download_bps'] ?? null) }}</p>
        </div>
        <div class="rounded-xl border border-sky-200 bg-gradient-to-br from-sky-50 to-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-800">Live upload</p>
            <p id="stat-upload" class="mt-2 text-2xl font-bold text-sky-950">{{ \App\Support\BandwidthDirection::formatBps($stats['upload_bps'] ?? null) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Today total</p>
            <p id="stat-today" class="mt-2 text-sm font-medium text-slate-800">
                ↓ {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['today_download'] ?? 0) }}
                · ↑ {{ \App\Models\BandwidthUsageDaily::formatBytes($stats['today_upload'] ?? 0) }}
            </p>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">Session</h2>
        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">IP address</dt><dd id="stat-ip" class="font-mono font-medium">{{ $stats['framed_ip'] ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Session download</dt><dd id="stat-session-down" class="font-medium">{{ \App\Models\BandwidthUsageDaily::formatBytes($stats['total_download'] ?? 0) }}</dd></div>
            <div><dt class="text-slate-500">Session upload</dt><dd id="stat-session-up" class="font-medium">{{ \App\Models\BandwidthUsageDaily::formatBytes($stats['total_upload'] ?? 0) }}</dd></div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">Speed updates every 30 seconds. ISP syncs routers every few minutes — browse for 1–2 minutes, then check again if speed shows “—”.</p>
    </div>

    <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">Last 12 hours (average Mbps)</h2>
        <canvas id="usage-chart" class="mt-4 h-48 w-full" height="160"></canvas>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            const initial = @json($stats);
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
                options: { responsive: true, scales: { y: { beginAtZero: true } } },
            });

            function formatBps(bps) {
                if (bps === null || bps === undefined) return '—';
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

            async function refreshLive() {
                try {
                    const res = await fetch(@json(route('portal.usage.live')), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    document.getElementById('stat-online').textContent = data.online ? 'Online' : 'Offline';
                    document.getElementById('stat-online').className = 'mt-2 text-xl font-bold ' + (data.online ? 'text-emerald-600' : 'text-slate-500');
                    document.getElementById('stat-download').textContent = formatBps(data.download_bps);
                    document.getElementById('stat-upload').textContent = formatBps(data.upload_bps);
                    document.getElementById('stat-today').textContent = '↓ ' + formatBytes(data.today_download) + ' · ↑ ' + formatBytes(data.today_upload);
                    document.getElementById('stat-ip').textContent = data.framed_ip || '—';
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
            setInterval(refreshLive, 30000);
        </script>
    @endpush
@endsection
