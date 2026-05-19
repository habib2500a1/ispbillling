@extends('portal.layout')

@section('title', 'ONU status')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">ONU optical status</h1>
            <p class="mt-1 text-sm text-slate-600">OLT থেকে live RX/TX — GPON signal health</p>
        </div>
        <p id="onu-updated" class="text-xs text-slate-500">Live</p>
    </div>

    @if (! ($onu['linked'] ?? false))
        <div class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-950">
            <p class="font-semibold">ONU linked নেই</p>
            <p class="mt-2">{{ $onu['hint'] ?? '' }}</p>
            <a href="{{ route('portal.tickets.create') }}" class="portal-btn-primary mt-4 inline-flex">Support ticket খুলুন</a>
        </div>
    @else
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border-2 border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6">
                <p class="text-xs font-bold uppercase text-slate-500">RX power (OLT → you)</p>
                <p id="onu-rx" class="mt-2 text-4xl font-bold tabular-nums text-slate-900">{{ $onu['rx_dbm'] ?? '—' }} <span class="text-lg">dBm</span></p>
                <p id="onu-rx-label" class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-bold portal-signal-{{ match($onu['color'] ?? 'gray') { 'success'=>'emerald','warning'=>'amber','danger'=>'rose',default=>'slate' } }}">
                    {{ $onu['rx_level_label'] ?? 'Unknown' }}
                </p>
                <div class="mt-6">
                    <p class="text-xs text-slate-500 mb-1">Fiber stability</p>
                    <div class="portal-gauge"><div id="onu-stability-bar" class="portal-gauge-fill bg-emerald-500" style="width: {{ $onu['stability_percent'] ?? 0 }}%"></div></div>
                    <p class="mt-1 text-sm font-semibold"><span id="onu-stability">{{ $onu['stability_percent'] ?? 0 }}</span>%</p>
                </div>
            </div>
            <div class="rounded-2xl border-2 border-slate-200 bg-white p-6">
                <p class="text-xs font-bold uppercase text-slate-500">TX power</p>
                <p id="onu-tx" class="mt-2 text-3xl font-bold tabular-nums">{{ $onu['tx_dbm'] ?? '—' }} <span class="text-base">dBm</span></p>
                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">ONU status</dt><dd id="onu-oper" class="font-medium capitalize">{{ $onu['oper_status'] ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">MAC</dt><dd class="font-mono">{{ $onu['mac'] ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Serial</dt><dd class="font-mono">{{ $onu['serial'] ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Label</dt><dd>{{ $onu['label'] ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Health score</dt><dd id="onu-health">{{ $onu['fiber_health_percent'] ?? 0 }}%</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last OLT poll</dt><dd id="onu-polled">{{ $onu['last_polled'] ?? '—' }}</dd></div>
                </dl>
                @if (! empty($onu['root_cause']))
                    <p class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800" id="onu-hint">Hint: {{ str_replace('_', ' ', $onu['root_cause']) }}</p>
                @endif
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            async function refreshOnu() {
                try {
                    const res = await fetch(@json(route('portal.onu.live')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) return;
                    const o = await res.json();
                    if (!o.linked) return;
                    document.getElementById('onu-rx').innerHTML = (o.rx_dbm ?? '—') + ' <span class="text-lg">dBm</span>';
                    document.getElementById('onu-tx').innerHTML = (o.tx_dbm ?? '—') + ' <span class="text-base">dBm</span>';
                    document.getElementById('onu-rx-label').textContent = o.rx_level_label || '—';
                    document.getElementById('onu-stability').textContent = o.stability_percent || 0;
                    document.getElementById('onu-stability-bar').style.width = (o.stability_percent || 0) + '%';
                    document.getElementById('onu-oper').textContent = o.oper_status || '—';
                    document.getElementById('onu-health').textContent = (o.fiber_health_percent || 0) + '%';
                    document.getElementById('onu-polled').textContent = o.last_polled || '—';
                    document.getElementById('onu-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
                } catch (e) {}
            }
            setInterval(refreshOnu, {{ (int) config('portal.poll_seconds', 5) * 1000 }});
        </script>
    @endpush
@endsection
