@extends('reseller.layout')

@section('title', $customer->name)

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-2xl font-bold">{{ $customer->name }}</h1>
        <p class="text-sm text-slate-600">{{ $customer->customer_code }} · {{ $customer->phone }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($reseller->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
                <a href="{{ route('reseller.customers.edit', $customer) }}" class="rsl-btn-sm rsl-btn-sm--outline">Edit</a>
            @endif
            @if ($reseller->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                <a href="{{ route('reseller.customers.collect', $customer) }}" class="rsl-btn-sm">Collect payment</a>
            @endif
            @if ($reseller->canPortal(\App\Support\ResellerPortalPermission::ONU_VIEW))
                <a href="{{ route('reseller.onu.show', $customer) }}" class="rsl-btn-sm rsl-btn-sm--outline">ONU signal</a>
            @endif
        </div>
    </div>
    <div class="rsl-kpi-grid mt-6">
        <div class="rsl-metric"><p class="rsl-metric-label">Package</p><p class="rsl-metric-value text-base">{{ $customer->package?->name ?? '—' }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Status</p><p class="rsl-metric-value text-base capitalize">{{ $customer->status }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Due</p><p class="rsl-metric-value text-rose-700">{{ number_format($customer->openInvoiceBalance(), 2) }} BDT</p></div>
    </div>
@endsection
