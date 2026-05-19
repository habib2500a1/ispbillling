@extends('portal.layout')

@section('title', 'Dashboard')

@php
    $conn = $dash['connection'] ?? [];
    $traffic = $dash['traffic'] ?? [];
    $onu = $dash['onu'] ?? [];
    $bill = $dash['billing'] ?? [];
    $pkg = $dash['package'] ?? null;
    $onuColor = match ($onu['color'] ?? 'gray') {
        'success' => 'emerald',
        'warning' => 'amber',
        'danger' => 'rose',
        default => 'slate',
    };
@endphp

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="bg-gradient-to-r from-violet-600 to-fuchsia-600 bg-clip-text text-3xl font-bold text-transparent">
                Hello, {{ $customer->name }}
            </h1>
            <p class="mt-1 text-sm text-slate-600">{{ $customer->customer_code }} · Live dashboard</p>
        </div>
        <p id="dash-updated" class="text-xs text-slate-500">Live</p>
    </div>

    @if (($movieServers ?? collect())->isNotEmpty())
        <div class="mt-6">
            <x-movie-servers-showcase :servers="$movieServers" variant="portal" />
        </div>
    @endif

    @if ($outages->isNotEmpty())
        <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">Area notices</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($outages as $o)
                    <li>{{ $o->title }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="portal-metric-card border-emerald-200">
            <div class="flex items-center gap-2">
                <span id="live-dot" class="portal-pulse {{ ($conn['online'] ?? false) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Connection</p>
            </div>
            <p id="stat-connection" class="mt-2 text-2xl font-bold {{ ($conn['online'] ?? false) ? 'text-emerald-600' : 'text-slate-500' }}">
                {{ $conn['status_label'] ?? '—' }}
            </p>
            <p id="stat-ppp" class="mt-1 text-sm text-slate-600">{{ $conn['router_status'] ?? '—' }}</p>
            <p class="mt-2 text-xs text-slate-500">IP: <span id="stat-ip" class="font-mono">{{ $conn['framed_ip'] ?? '—' }}</span></p>
            <p class="text-xs text-slate-500">Uptime: <span id="stat-uptime">{{ $conn['session_uptime'] ?? '—' }}</span></p>
            <p class="text-xs text-slate-500">Last online: <span id="stat-last">{{ $conn['last_online'] ?? '—' }}</span></p>
        </div>

        <div class="portal-metric-card border-cyan-200">
            <p class="text-xs font-bold uppercase tracking-wide text-cyan-800">Live speed</p>
            <p class="mt-2 text-sm text-slate-600">↓ <span id="stat-down" class="text-xl font-bold text-amber-900">{{ $traffic['download_human'] ?? '—' }}</span></p>
            <p class="text-sm text-slate-600">↑ <span id="stat-up" class="text-xl font-bold text-sky-900">{{ $traffic['upload_human'] ?? '—' }}</span></p>
            <a href="{{ route('portal.usage.index') }}" class="mt-3 inline-block text-xs font-semibold text-violet-600 hover:underline">Open traffic monitor →</a>
        </div>

        <div class="portal-metric-card border-slate-200">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">ONU signal</p>
            @if ($onu['linked'] ?? false)
                <p class="mt-2 text-2xl font-bold text-slate-800">
                    <span id="stat-rx">{{ $onu['rx_dbm'] !== null ? $onu['rx_dbm'].' dBm' : '—' }}</span>
                </p>
                <p class="text-sm text-slate-600">TX: <span id="stat-tx">{{ $onu['tx_dbm'] !== null ? $onu['tx_dbm'].' dBm' : '—' }}</span></p>
                <p id="stat-signal-level" class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-bold portal-signal-{{ $onuColor }}">
                    {{ $onu['rx_level_label'] ?? 'Unknown' }}
                </p>
                <p class="mt-2 text-xs text-slate-500">Stability: <span id="stat-stability">{{ $onu['stability_percent'] ?? 0 }}%</span></p>
            @else
                <p class="mt-2 text-sm text-slate-600">{{ $onu['hint'] ?? 'ONU not linked' }}</p>
                <a href="{{ route('portal.tickets.create') }}" class="mt-2 inline-block text-xs font-semibold text-violet-600">Contact support</a>
            @endif
            <a href="{{ route('portal.onu.index') }}" class="mt-2 block text-xs font-semibold text-violet-600 hover:underline">ONU details →</a>
        </div>

        <div class="portal-metric-card border-violet-200">
            <p class="text-xs font-bold uppercase tracking-wide text-violet-800">Current due</p>
            <p id="stat-due" class="mt-2 text-3xl font-bold tabular-nums text-rose-600">{{ number_format($bill['total_due'] ?? 0, 0) }} BDT</p>
            <p class="text-xs text-slate-500">Due date: <span id="stat-due-date">{{ $bill['next_due_date'] ?? '—' }}</span></p>
            <a href="{{ route('portal.bills.index') }}" class="portal-btn-primary mt-3 text-xs">Pay bill</a>
        </div>

        <div class="portal-metric-card border-fuchsia-200">
            <p class="text-xs font-bold uppercase tracking-wide text-fuchsia-800">Wallet</p>
            <p id="stat-wallet" class="mt-2 text-2xl font-bold text-fuchsia-700">{{ number_format($bill['wallet_balance'] ?? 0, 2) }} BDT</p>
            @if ($pkg)
                <p class="mt-2 text-sm font-semibold text-slate-800">{{ $pkg['name'] }}</p>
                <p class="text-xs text-slate-500">{{ $pkg['download_mbps'] }} Mbps · Expires {{ $pkg['expires_at'] ?? '—' }}</p>
            @endif
        </div>

        <div class="portal-metric-card border-indigo-200">
            <p class="text-xs font-bold uppercase tracking-wide text-indigo-800">Quick actions</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('portal.speed-test.index') }}" class="rounded-lg bg-indigo-100 px-3 py-1.5 text-xs font-semibold text-indigo-800">Speed test</a>
                <a href="{{ route('portal.tickets.create') }}" class="rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-900">Support</a>
                <a href="{{ route('portal.notifications.index') }}" class="rounded-lg bg-violet-100 px-3 py-1.5 text-xs font-semibold text-violet-800">
                    Alerts @if(($dash['notifications_count'] ?? 0) > 0)({{ $dash['notifications_count'] }})@endif
                </a>
                @if (config('portal.whatsapp_url'))
                    <a href="{{ config('portal.whatsapp_url') }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-800">WhatsApp</a>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="text-sm font-bold text-slate-800">Live bandwidth (12h)</h2>
        <canvas id="dash-chart" class="mt-3 h-40 w-full" height="140"></canvas>
    </div>

    <div class="mt-8">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">Recent bills</h2>
            <a href="{{ route('portal.bills.index') }}" class="text-sm font-semibold text-violet-600 hover:underline">View all</a>
        </div>
        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Invoice</th><th class="px-4 py-3 text-right">Due</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($recentInvoices as $inv)
                        @php $due = round((float) $inv->total - (float) $inv->amount_paid, 2); @endphp
                        <tr>
                            <td class="px-4 py-3"><a href="{{ route('portal.invoices.show', $inv) }}" class="font-mono text-violet-600 hover:underline">{{ $inv->invoice_number }}</a></td>
                            <td class="px-4 py-3 text-right font-semibold {{ $due > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($due, 2) }}</td>
                            <td class="px-4 py-3 capitalize">{{ $inv->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">No bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            const pollMs = {{ (int) config('portal.poll_seconds', 5) * 1000 }};
            let dash = @json($dash);
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
                dot.className = 'portal-pulse ' + (on ? 'bg-emerald-500' : 'bg-slate-400');
                stat.textContent = on ? 'Online' : 'Offline';
                stat.className = 'mt-2 text-2xl font-bold ' + (on ? 'text-emerald-600' : 'text-slate-500');
            }

            async function refresh() {
                try {
                    const res = await fetch(@json(route('portal.dashboard.live')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) return;
                    dash = await res.json();
                    const c = dash.connection, t = dash.traffic, o = dash.onu, b = dash.billing;
                    setOnline(c.online);
                    document.getElementById('stat-ppp').textContent = c.router_status || '—';
                    document.getElementById('stat-ip').textContent = c.framed_ip || '—';
                    document.getElementById('stat-uptime').textContent = c.session_uptime || '—';
                    document.getElementById('stat-last').textContent = c.last_online || '—';
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
@endsection
