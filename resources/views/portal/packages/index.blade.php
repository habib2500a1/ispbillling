@extends('portal.layout')

@section('title', 'Packages')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Internet packages</h1>
            <p class="portal-page-lead">Compare public plans, review upgrade cost estimates, and request package changes directly from your portal.</p>
        </div>
        <a href="{{ route('portal.profile.index') }}" class="portal-card-button">Back to profile</a>
    </div>

    @if ($errors->any())
        <div class="portal-note-banner portal-note-banner--danger">{{ $errors->first() }}</div>
    @endif

    @if (($mustClearBalance ?? true) && ($openBalance ?? 0) > 0)
        <div class="portal-note-banner">
            <p class="font-semibold">Outstanding bill: {{ number_format($openBalance, 2) }} BDT</p>
            <p class="mt-1">Pay your current bill before changing package.</p>
            <a href="{{ route('portal.bills.index') }}" class="portal-link">Go to My bills →</a>
        </div>
    @endif

    @if (session('status'))
        <div class="portal-note-banner" style="border-color: rgba(16, 185, 129, 0.22); background: linear-gradient(135deg, rgba(236, 253, 245, 0.94), rgba(255, 255, 255, 0.98)); color: #065f46;">
            {{ session('status') }}
        </div>
    @endif

    <div class="portal-summary-grid portal-summary-grid--wide">
        <article class="portal-summary-card {{ $customer->package ? 'portal-summary-card--ok' : 'portal-summary-card--warn' }}">
            <p class="portal-summary-card__eyebrow">Current plan</p>
            <p class="portal-summary-card__value">{{ $customer->package?->name ?? 'Not assigned' }}</p>
            <p class="portal-summary-card__meta">
                @if ($customer->package)
                    {{ $customer->package->download_mbps }} Mbps · {{ number_format((float) $customer->package->price_monthly, 0) }} BDT/month
                @else
                    Contact support if your package is missing.
                @endif
            </p>
        </article>
        <article class="portal-summary-card {{ (($mustClearBalance ?? true) && ($openBalance ?? 0) > 0) ? 'portal-summary-card--due' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Open balance</p>
            <p class="portal-summary-card__value">{{ number_format($openBalance ?? 0, 2) }} BDT</p>
            <p class="portal-summary-card__meta">{{ (($mustClearBalance ?? true) && ($openBalance ?? 0) > 0) ? 'Balance must be cleared before package change.' : 'You can request a package change from this page.' }}</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Public plans</p>
            <p class="portal-summary-card__value">{{ $packages->count() }}</p>
            <p class="portal-summary-card__meta">Published package options available to customers right now.</p>
        </article>
        <article class="portal-summary-card {{ $customer->pendingPackage ? 'portal-summary-card--warn' : 'portal-summary-card--info' }}">
            <p class="portal-summary-card__eyebrow">Pending change</p>
            <p class="portal-summary-card__value">{{ $customer->pendingPackage?->name ?? 'None' }}</p>
            <p class="portal-summary-card__meta">
                @if ($customer->pendingPackage)
                    Effective on {{ $customer->pending_package_effective_date?->format('d M Y') }}
                @else
                    No scheduled change request is waiting right now.
                @endif
            </p>
        </article>
    </div>

    <div class="portal-package-grid">
        @foreach ($packages as $pkg)
            @php $quote = $quotesByPackage[$pkg->id] ?? null; @endphp
            <article class="portal-package-card {{ (int) $pkg->id === (int) $currentPackageId ? 'portal-package-card--active' : '' }}">
                @if ((int) $pkg->id === (int) $currentPackageId)
                    <span class="portal-status-pill portal-status-pill--info">Current</span>
                @endif
                <h3 class="mt-3 text-lg font-bold text-slate-900">{{ $pkg->name }}</h3>
                <p class="portal-package-price">{{ number_format((float) $pkg->price_monthly, 0) }} <span>BDT/mo</span></p>
                <ul class="portal-feature-list">
                    <li>↓ {{ $pkg->download_mbps }} Mbps</li>
                    @if ($pkg->upload_mbps)<li>↑ {{ $pkg->upload_mbps }} Mbps</li>@endif
                    @if ($pkg->included_data_gb)
                        <li>{{ $pkg->included_data_gb }} GB/day FUP</li>
                        @if ($pkg->overage_price_per_gb)
                            <li>Overage: {{ number_format((float) $pkg->overage_price_per_gb, 0) }} BDT/GB</li>
                        @endif
                    @endif
                </ul>

                @if ($quote)
                    <div class="portal-quote-box">
                        @if ($quote['is_upgrade'])
                            <p class="font-semibold">Upgrade estimate ({{ $quote['days_remaining'] }} days left)</p>
                            <p>Pay now: <strong>{{ number_format($quote['net_due'], 2) }} BDT</strong></p>
                            <p>Credit {{ number_format($quote['credit_amount'], 2) }} · New {{ number_format($quote['new_charge'], 2) }}</p>
                        @else
                            <p class="font-semibold">Downgrade</p>
                            <p>{{ $quote['effective_label'] }}</p>
                        @endif
                    </div>
                @endif

                @if ((int) $pkg->id !== (int) $currentPackageId)
                    @php $blocked = ($mustClearBalance ?? true) && ($openBalance ?? 0) > 0; @endphp
                    <form method="post" action="{{ route('portal.packages.request') }}" class="portal-form-grid mt-4">
                        @csrf
                        <input type="hidden" name="package_id" value="{{ $pkg->id }}">
                        <div>
                            <label for="note-{{ $pkg->id }}">Note</label>
                            <textarea id="note-{{ $pkg->id }}" name="note" rows="2" placeholder="Optional note" @disabled($blocked)></textarea>
                        </div>
                        <button type="submit" class="portal-btn-primary w-full text-sm" @disabled($blocked)>
                            @if ($quote && $quote['is_upgrade'] && $quote['net_due'] > 0)
                                Upgrade — pay {{ number_format($quote['net_due'], 0) }} BDT
                            @elseif ($quote && $quote['is_upgrade'])
                                Upgrade now
                            @else
                                Request this plan
                            @endif
                        </button>
                    </form>
                @endif
            </article>
        @endforeach
    </div>
@endsection
