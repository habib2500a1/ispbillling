@extends('reseller.layout')

@section('title', 'ONU — '.$customer->name)

@section('content')
    @include('reseller.partials.page-header', [
        'title' => $customer->name,
        'subtitle' => 'ONU signal & poll status',
        'backUrl' => route('reseller.onu.index'),
    ])

    <div class="rsl-panel rsl-panel-pad">
        @if ($onu['linked'] ?? false)
            <div class="rsl-metric-grid">
                <div class="rsl-metric">
                    <p class="rsl-metric-label">RX</p>
                    <p class="rsl-metric-value">{{ $onu['rx_dbm'] }} dBm</p>
                </div>
                <div class="rsl-metric">
                    <p class="rsl-metric-label">TX</p>
                    <p class="rsl-metric-value">{{ $onu['tx_dbm'] ?? '—' }} dBm</p>
                </div>
                <div class="rsl-metric">
                    <p class="rsl-metric-label">Level</p>
                    <p class="rsl-metric-value" style="font-size:1rem">{{ $onu['rx_level_label'] }}</p>
                </div>
                <div class="rsl-metric">
                    <p class="rsl-metric-label">Polled</p>
                    <p class="rsl-metric-value" style="font-size:1rem">{{ $onu['last_polled'] ?? '—' }}</p>
                </div>
            </div>
        @else
            <p style="color:var(--rsl-text-muted)">{{ $onu['hint'] ?? 'No ONU linked.' }}</p>
        @endif
    </div>
@endsection
