@extends('portal.layout')

@section('title', 'Equipment')

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">Network equipment</h1>
    <p class="mt-1 text-sm text-slate-600">ONU / CPE linked to your account and the OLT serving you.</p>

    @if ($olts->isNotEmpty())
        <div class="mt-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Your OLT(s)</h2>
            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Serial</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Mgmt IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($olts as $olt)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $olt->adminLabel() }}</td>
                                <td class="px-4 py-3 font-mono text-slate-700">{{ $olt->serial_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $olt->location ?: '—' }}</td>
                                <td class="px-4 py-3 font-mono text-slate-700">{{ $olt->management_ip ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Your devices</h2>
        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Label</th>
                        <th class="px-4 py-3">Serial</th>
                        <th class="px-4 py-3">OLT</th>
                        <th class="px-4 py-3">PON port</th>
                        <th class="px-4 py-3 text-center">Card</th>
                        <th class="px-4 py-3 text-center">PON</th>
                        <th class="px-4 py-3 text-center">ONU #</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Offline / fault</th>
                        <th class="px-4 py-3 text-right">RX dBm</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($devices as $d)
                        <tr>
                            <td class="px-4 py-3 capitalize text-slate-800">{{ $d->type }}</td>
                            <td class="px-4 py-3 text-slate-800">{{ $d->display_name ?: '—' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-700">{{ $d->serial_number }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $d->olt ? $d->olt->adminLabel() : '—' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-700">{{ $d->oltPort ? $d->oltPort->label : '—' }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-slate-600">{{ $d->card_no ?? '—' }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-slate-600">{{ $d->pon_no ?? '—' }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-slate-600">{{ $d->onu_index ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize text-slate-800">{{ str_replace('_', ' ', $d->onu_oper_status ?? 'unknown') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $d->offline_reason ? \Illuminate\Support\Str::limit($d->offline_reason, 80) : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $d->rx_power_dbm !== null ? number_format((float) $d->rx_power_dbm, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-center text-slate-500">No equipment registered yet. Contact support if this is unexpected.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
