@extends('reseller.layout')

@section('title', 'Enterprise hub')

@section('content')
    <div class="rsl-hero">
        <div class="rsl-hero-inner">
            <p class="rsl-hero-badge">Enterprise partner portal</p>
            <h1 class="rsl-hero-title">{{ $reseller->displayName() }}</h1>
            <p class="rsl-hero-sub">{{ $reseller->code }} · {{ $reseller->franchiseTypeLabel() }} · Depth {{ $reseller->hierarchy_depth }}</p>
            <div class="rsl-hero-wallets">
                <div class="rsl-hero-wallet">
                    <span class="rsl-hero-wallet-label">Main</span>
                    <span class="rsl-hero-wallet-value">{{ number_format((float) $reseller->wallet_balance, 0) }} BDT</span>
                </div>
                @if ((float) $reseller->bonus_wallet_balance > 0)
                    <div class="rsl-hero-wallet rsl-hero-wallet--bonus">
                        <span class="rsl-hero-wallet-label">Bonus</span>
                        <span class="rsl-hero-wallet-value">{{ number_format((float) $reseller->bonus_wallet_balance, 0) }} BDT</span>
                    </div>
                @endif
                @if ((float) $reseller->credit_limit > 0)
                    <div class="rsl-hero-wallet rsl-hero-wallet--credit">
                        <span class="rsl-hero-wallet-label">Credit</span>
                        <span class="rsl-hero-wallet-value">{{ number_format((float) $reseller->credit_limit, 0) }} BDT</span>
                    </div>
                @endif
                @if ((float) ($reseller->admin_receivable_due ?? 0) > 0)
                    <div class="rsl-hero-wallet">
                        <span class="rsl-hero-wallet-label">HQ due</span>
                        <span class="rsl-hero-wallet-value">{{ number_format((float) $reseller->admin_receivable_due, 0) }} BDT</span>
                    </div>
                @endif
                <div class="rsl-hero-wallet">
                    <span class="rsl-hero-wallet-label">Available</span>
                    <span class="rsl-hero-wallet-value">{{ number_format($availableMain, 0) }} BDT</span>
                </div>
            </div>
            @if ($isLowBalance)
                <p class="rsl-hero-alert">⚠ Low balance — recharge to avoid auto-suspend.</p>
            @endif
        </div>
    </div>

    @if ($announcements->isNotEmpty())
        <div class="rsl-card mt-6 p-5">
            <h2 class="rsl-heading text-sm">Latest from HQ</h2>
            @foreach ($announcements as $item)
                <div class="mt-3 border-t border-slate-200 pt-3 first:mt-2 first:border-0 first:pt-0">
                    <p class="font-semibold rsl-text">{{ $item->title }}</p>
                    <p class="text-sm rsl-text-muted line-clamp-2">{{ $item->body }}</p>
                </div>
            @endforeach
            <a href="{{ route('reseller.announcements.index') }}" class="rsl-link mt-3 inline-block text-sm">All announcements →</a>
        </div>
    @endif

    <div class="rsl-tool-grid mt-6">
        @foreach ($tools as $tool)
            @if (! \Illuminate\Support\Facades\Route::has($tool['route']))
                @continue
            @endif
            <a href="{{ route($tool['route']) }}" class="rsl-tool-card">
                <span class="rsl-tool-icon">{{ $tool['icon'] }}</span>
                <span class="rsl-tool-label">{{ $tool['label'] }}</span>
                <span class="rsl-tool-desc">{{ $tool['desc'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="rsl-card mt-6 p-5">
        <h2 class="rsl-heading text-sm">Resource quotas</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><span class="rsl-text-muted">Customers</span><br><strong>{{ $quota['customers'] }}@if (! empty($quota['limits']['max_clients'])) / {{ $quota['limits']['max_clients'] }}@endif</strong></div>
            <div><span class="rsl-text-muted">Active</span><br><strong>{{ $quota['active_customers'] }}@if (! empty($quota['limits']['max_active_clients'])) / {{ $quota['limits']['max_active_clients'] }}@endif</strong></div>
            <div><span class="rsl-text-muted">ONU</span><br><strong>{{ $quota['onu'] }}@if (! empty($quota['limits']['max_onu'])) / {{ $quota['limits']['max_onu'] }}@endif</strong></div>
            <div><span class="rsl-text-muted">Packages</span><br><strong>{{ $quota['packages'] }}@if (! empty($quota['limits']['max_packages'])) / {{ $quota['limits']['max_packages'] }}@endif</strong></div>
        </div>
    </div>
@endsection
