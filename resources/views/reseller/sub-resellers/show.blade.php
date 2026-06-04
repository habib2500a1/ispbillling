@extends('reseller.layout')

@section('title', $partner->name)

@section('content')
    @include('reseller.partials.page-header', [
        'title' => $partner->name,
        'subtitle' => $partner->code.' · '.$partner->franchiseTypeLabel(),
        'backUrl' => route('reseller.sub-resellers.index'),
        'backLabel' => '← Partners',
    ])

    <div class="rsl-metric-grid">
        <div class="rsl-metric">
            <p class="rsl-metric-label">Subscribers</p>
            <p class="rsl-metric-value">{{ $stats['customers'] }}</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Sub-partners</p>
            <p class="rsl-metric-value">{{ $stats['sub_resellers'] }}</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Wallet</p>
            <p class="rsl-metric-value">{{ number_format($stats['wallet'], 0) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Pending commission</p>
            <p class="rsl-metric-value">{{ number_format($stats['pending_commission'], 0) }} BDT</p>
        </div>
    </div>

    <div class="rsl-panel rsl-panel-pad mt-6">
        <h2 class="rsl-panel-title">Contact</h2>
        <p class="mt-2 text-sm" style="color:var(--rsl-text-muted)">{{ $partner->phone ?: '—' }} · {{ $partner->email ?: '—' }}</p>
        <p class="mt-1 text-sm" style="color:var(--rsl-text-muted)">Commission: {{ $partner->commissionLabel() }}</p>
    </div>
@endsection
