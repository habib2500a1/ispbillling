@extends('reseller.layout')

@section('title', 'Network')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">PPPoE sessions</h1>
        <p class="rsl-subtitle mt-1">Live download/upload rates and session uptime from MikroTik polling.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('reseller.network.index', ['filter' => 'online']) }}" class="rsl-btn-sm {{ $filter === 'online' ? '' : 'rsl-btn-sm--outline' }}">Online ({{ $onlineCount }})</a>
            <a href="{{ route('reseller.network.index', ['filter' => 'offline']) }}" class="rsl-btn-sm {{ $filter === 'offline' ? '' : 'rsl-btn-sm--outline' }}">Offline active ({{ $offlineCount }})</a>
            <a href="{{ route('reseller.network.index', ['filter' => 'all']) }}" class="rsl-btn-sm {{ $filter === 'all' ? '' : 'rsl-btn-sm--outline' }}">All</a>
        </div>
    </div>
    <div class="rsl-card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-sm" id="network-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Subscriber</th>
                        <th class="px-4 py-3 text-left">Package</th>
                        <th class="px-4 py-3 text-left">IP</th>
                        <th class="px-4 py-3 text-left">Uptime</th>
                        <th class="px-4 py-3 text-left">Live ↓ / ↑</th>
                        <th class="px-4 py-3 text-left">Session data</th>
                        <th class="px-4 py-3 text-left">Router</th>
                        <th class="px-4 py-3 text-left"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        @php $s = $sessionMap[$client->id] ?? []; @endphp
                        <tr data-customer-id="{{ $client->id }}" data-online="{{ ($s['online'] ?? false) ? '1' : '0' }}" @if($filter === 'online' && ($s['online'] ?? false)) data-session-url="{{ route('reseller.network.session', $client) }}" @endif>
                            <td class="px-4 py-3">
                                <a href="{{ route('reseller.customers.show', $client) }}" class="rsl-link">{{ $client->name }}</a>
                                <br><span class="text-xs rsl-text-muted">{{ $client->customer_code }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $client->package?->name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs" data-field="ip">{{ $s['framed_ip'] ?? '—' }}</td>
                            <td class="px-4 py-3" data-field="uptime">{{ $s['uptime'] ?? '—' }}</td>
                            <td class="px-4 py-3" data-field="live">
                                @if ($s['online'] ?? false)
                                    <span class="text-emerald-700">{{ $s['download_human'] ?? '—' }}</span>
                                    <span class="rsl-text-muted"> / </span>
                                    <span class="text-sky-700">{{ $s['upload_human'] ?? '—' }}</span>
                                @else
                                    <span class="rsl-text-muted">Offline@if(!empty($s['last_disconnect'])) · last {{ $s['last_disconnect'] }}@endif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs rsl-text-muted" data-field="session-bytes">
                                @if ($s['online'] ?? false)
                                    ↓ {{ $s['session_download'] ?? '—' }} · ↑ {{ $s['session_upload'] ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $s['router'] ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($client->is_ppp_online)
                                    <form method="post" action="{{ route('reseller.network.disconnect', $client) }}" class="inline" onsubmit="return confirm('Disconnect session?')">@csrf<button type="submit" class="rsl-link text-xs">Kick</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center rsl-text-muted">No subscribers in this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $clients->links() }}</div>
    </div>

    @if ($filter === 'online' && $clients->isNotEmpty())
        <p class="mt-2 text-xs rsl-text-muted text-center">Live rates refresh every 15 seconds on this page.</p>
        <script>
            (function () {
                const rows = document.querySelectorAll('#network-table tbody tr[data-online="1"]');
                if (!rows.length) return;

                async function refreshRow(row) {
                    const url = row.getAttribute('data-session-url');
                    if (!url) return;
                    try {
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        const live = row.querySelector('[data-field="live"]');
                        const uptime = row.querySelector('[data-field="uptime"]');
                        const bytes = row.querySelector('[data-field="session-bytes"]');
                        const ip = row.querySelector('[data-field="ip"]');
                        if (ip && data.framed_ip) ip.textContent = data.framed_ip;
                        if (uptime) uptime.textContent = data.uptime || '—';
                        if (live && data.online) {
                            live.innerHTML = '<span class="text-emerald-700">' + (data.download_human || '—') + '</span> <span class="rsl-text-muted">/</span> <span class="text-sky-700">' + (data.upload_human || '—') + '</span>';
                        }
                        if (bytes && data.online) {
                            bytes.textContent = '↓ ' + (data.session_download || '—') + ' · ↑ ' + (data.session_upload || '—');
                        }
                    } catch (e) {}
                }

                function tick() {
                    rows.forEach(refreshRow);
                }

                tick();
                setInterval(tick, 15000);
            })();
        </script>
    @endif
@endsection
