@extends('reseller.layout')

@section('title', 'ONU')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'ONU / GPON',
        'subtitle' => 'Live optical levels for subscribers',
    ])

    <div class="rsl-panel">
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-sm text-left">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Subscriber</th>
                        <th class="px-4 py-3">RX</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3">
                                {{ $row['customer']->name }}<br>
                                <span class="text-xs" style="color:var(--rsl-text-muted)">{{ $row['customer']->customer_code }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $row['onu']['linked'] ? ($row['onu']['rx_dbm'] ?? '—').' dBm' : '—' }}</td>
                            <td class="px-4 py-3">{{ $row['onu']['rx_level_label'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('reseller.onu.show', $row['customer']) }}" class="rsl-link-action">Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">No ONU data yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
