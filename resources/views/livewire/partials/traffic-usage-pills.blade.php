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
        <strong>{{ $u['month_total_label'] ?? '0 B' }}</strong>
    </div>
</div>
