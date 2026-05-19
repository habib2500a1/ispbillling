@extends('portal.layout')

@section('title', 'Packages')

@section('content')
    <h1 class="text-2xl font-bold text-fuchsia-800">Internet packages</h1>
    <p class="mt-1 text-sm text-slate-600">Only plans published for customers are listed. Upgrades need payment; clear any open bill first.</p>

    @if ($errors->any())
        <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</p>
    @endif

    @if (($mustClearBalance ?? true) && ($openBalance ?? 0) > 0)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Outstanding bill: {{ number_format($openBalance, 2) }} BDT</p>
            <p class="mt-1">Pay your current bill before changing package.</p>
            <a href="{{ route('portal.bills.index') }}" class="mt-2 inline-block font-semibold text-violet-700 hover:underline">Go to My bills →</a>
        </div>
    @endif

    @if (session('status'))
        <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    @if ($customer->package)
        <div class="mt-6 rounded-xl bg-gradient-to-r from-fuchsia-500 to-violet-600 p-5 text-white shadow-lg">
            <p class="text-sm opacity-90">Your current plan</p>
            <p class="text-xl font-bold">{{ $customer->package->name }}</p>
            <p class="text-sm">{{ $customer->package->download_mbps }} Mbps down · {{ number_format((float) $customer->package->price_monthly, 0) }} BDT/month</p>
            @if ($customer->package->included_data_gb)
                <p class="mt-1 text-xs opacity-90">Daily data allowance: {{ $customer->package->included_data_gb }} GB</p>
            @endif
            @if ($customer->pendingPackage)
                <p class="mt-2 rounded-lg bg-white/20 px-3 py-2 text-xs">
                    Changing to <strong>{{ $customer->pendingPackage->name }}</strong>
                    on {{ $customer->pending_package_effective_date?->format('d M Y') }}
                </p>
            @endif
        </div>
    @endif

    <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($packages as $pkg)
            @php $quote = $quotesByPackage[$pkg->id] ?? null; @endphp
            <div class="portal-card {{ (int) $pkg->id === (int) $currentPackageId ? 'ring-2 ring-fuchsia-500' : '' }} p-5">
                @if ((int) $pkg->id === (int) $currentPackageId)
                    <span class="portal-badge bg-fuchsia-100 text-fuchsia-800">Current</span>
                @endif
                <h3 class="mt-2 text-lg font-bold text-slate-900">{{ $pkg->name }}</h3>
                <p class="mt-1 text-3xl font-bold text-violet-600">{{ number_format((float) $pkg->price_monthly, 0) }} <span class="text-sm font-normal text-slate-500">BDT/mo</span></p>
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
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
                    <div class="mt-3 rounded-lg border border-violet-100 bg-violet-50/80 px-3 py-2 text-xs text-violet-900">
                        @if ($quote['is_upgrade'])
                            <p class="font-semibold">Upgrade estimate ({{ $quote['days_remaining'] }} days left)</p>
                            <p>Pay now: <strong>{{ number_format($quote['net_due'], 2) }} BDT</strong></p>
                            <p class="text-violet-700">Credit {{ number_format($quote['credit_amount'], 2) }} · New {{ number_format($quote['new_charge'], 2) }}</p>
                        @else
                            <p class="font-semibold">Downgrade</p>
                            <p>{{ $quote['effective_label'] }}</p>
                        @endif
                    </div>
                @endif

                @if ((int) $pkg->id !== (int) $currentPackageId)
                    @php $blocked = ($mustClearBalance ?? true) && ($openBalance ?? 0) > 0; @endphp
                    <form method="post" action="{{ route('portal.packages.request') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="package_id" value="{{ $pkg->id }}">
                        <textarea name="note" rows="2" placeholder="Optional note…" class="mb-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" @disabled($blocked)></textarea>
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
            </div>
        @endforeach
    </div>
@endsection
