@extends('reseller.layout')

@section('title', $partner->name)

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">{{ $partner->name }}</h1>
        <p class="rsl-subtitle">{{ $partner->code }} · {{ $partner->franchiseTypeLabel() }}</p>
        <a href="{{ route('reseller.sub-resellers.index') }}" class="rsl-link mt-3 inline-block">← Back to sub-partners</a>
    </div>
    <div class="rsl-kpi-grid mt-6">
        <div class="rsl-metric"><p class="rsl-metric-label">Clients</p><p class="rsl-metric-value">{{ $stats['customers'] }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Sub-partners</p><p class="rsl-metric-value">{{ $stats['sub_resellers'] }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Wallet</p><p class="rsl-metric-value text-sky-700">{{ number_format($stats['wallet'], 0) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Pending commission</p><p class="rsl-metric-value text-amber-700">{{ number_format($stats['pending_commission'], 0) }} BDT</p></div>
    </div>
    <div class="rsl-card mt-6 p-6">
        <h2 class="rsl-heading mb-2">Contact</h2>
        <p class="rsl-text-muted text-sm">{{ $partner->phone ?: '—' }} · {{ $partner->email ?: '—' }}</p>
        <p class="rsl-text-muted text-sm mt-1">Commission: {{ $partner->commissionLabel() }}</p>
    </div>
@endsection
