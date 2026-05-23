@extends('reseller.layout')

@section('title', 'ONU')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-xl font-bold">{{ $customer->name }}</h1>
        @if ($onu['linked'] ?? false)
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rsl-metric"><p class="rsl-metric-label">RX</p><p class="rsl-metric-value">{{ $onu['rx_dbm'] }} dBm</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">TX</p><p class="rsl-metric-value">{{ $onu['tx_dbm'] ?? '—' }} dBm</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">Level</p><p class="rsl-metric-value text-base">{{ $onu['rx_level_label'] }}</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">Polled</p><p class="rsl-metric-value text-base">{{ $onu['last_polled'] ?? '—' }}</p></div>
            </div>
        @else
            <p class="mt-4 text-slate-600">{{ $onu['hint'] ?? 'No ONU linked.' }}</p>
        @endif
    </div>
@endsection
