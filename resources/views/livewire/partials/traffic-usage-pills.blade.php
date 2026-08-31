@php
    $u = $usage ?? [];
    $compact = $compact ?? false;
@endphp
<div class="tu-pills {{ $compact ? 'tu-pills-compact' : '' }}">
    <div class="tu-pill">
        <span>{{ $u['live_or_last_title'] ?? __('Session') }}</span>
        <strong>{{ $u['live_or_last_label'] ?? '0 B' }}</strong>
    </div>
    <div class="tu-pill">
        <span>{{ __('Today') }}</span>
        <strong>{{ $u['day_total_label'] ?? '0 B' }}</strong>
    </div>
    <div class="tu-pill tu-pill-month">
        <span>{{ __('This month') }}</span>
        <strong title="{{ __('Download') }}: {{ $u['month_tx_label'] ?? '0 B' }} · {{ __('Upload') }}: {{ $u['month_rx_label'] ?? '0 B' }}">{{ $u['month_total_label'] ?? '0 B' }}</strong>
        @if (! $compact)
            <small class="d-block text-muted" style="font-size:.68rem;">↓ {{ $u['month_tx_label'] ?? '0 B' }} · ↑ {{ $u['month_rx_label'] ?? '0 B' }}</small>
        @endif
    </div>
</div>
