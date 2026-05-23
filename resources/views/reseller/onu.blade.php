@extends('reseller.layout')

@section('title', 'ONU monitoring')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-xl font-bold">ONU / GPON</h1>
        <p class="text-sm text-slate-600">Live optical levels for your subscribers</p>
    </div>
    <div class="rsl-card mt-6 overflow-hidden">
        <table class="rsl-table w-full text-sm">
            <thead class="bg-slate-50 border-b"><tr><th class="px-4 py-3 text-left">Subscriber</th><th class="px-4 py-3">RX</th><th class="px-4 py-3">Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3">{{ $row['customer']->name }}<br><span class="text-xs text-slate-500">{{ $row['customer']->customer_code }}</span></td>
                        <td class="px-4 py-3">{{ $row['onu']['linked'] ? ($row['onu']['rx_dbm'] ?? '—').' dBm' : '—' }}</td>
                        <td class="px-4 py-3">{{ $row['onu']['rx_level_label'] ?? '—' }}</td>
                        <td class="px-4 py-3"><a href="{{ route('reseller.onu.show', $row['customer']) }}" class="text-indigo-600 font-semibold">Details</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
