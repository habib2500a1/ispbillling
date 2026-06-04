@extends('reseller.layout')

@section('title', 'Wallet overview')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Wallet overview',
        'subtitle' => 'Balances, quotas, and ledger.',
        'backUrl' => route('reseller.wallet'),
        'backLabel' => '← Wallet',
    ])

    <div class="rsl-metric-grid">
        <div class="rsl-metric">
            <p class="rsl-metric-label">Main Wallet</p>
            <p class="rsl-metric-value">{{ number_format((float) $reseller->wallet_balance, 2) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Bonus</p>
            <p class="rsl-metric-value">{{ number_format((float) $reseller->bonus_wallet_balance, 2) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Credit limit</p>
            <p class="rsl-metric-value">{{ number_format((float) $reseller->credit_limit, 2) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Available</p>
            <p class="rsl-metric-value">{{ number_format($availableMain, 2) }} BDT</p>
        </div>
    </div>

    @if ($isLowBalance)
        <div class="rsl-callout rsl-callout--due mt-4">Low balance — recharge soon to avoid auto-suspend.</div>
    @endif

    <div class="rsl-panel rsl-panel-pad mt-6">
        <h2 class="rsl-panel-title">Resource quotas</h2>
        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="rsl-metric-label">Customers</dt><dd class="font-semibold">{{ $quota['customers'] }}@if (! empty($quota['limits']['max_clients'])) / {{ $quota['limits']['max_clients'] }}@endif</dd></div>
            <div><dt class="rsl-metric-label">Active</dt><dd class="font-semibold">{{ $quota['active_customers'] }}@if (! empty($quota['limits']['max_active_clients'])) / {{ $quota['limits']['max_active_clients'] }}@endif</dd></div>
            <div><dt class="rsl-metric-label">ONU</dt><dd class="font-semibold">{{ $quota['onu'] }}@if (! empty($quota['limits']['max_onu'])) / {{ $quota['limits']['max_onu'] }}@endif</dd></div>
            <div><dt class="rsl-metric-label">Packages</dt><dd class="font-semibold">{{ $quota['packages'] }}@if (! empty($quota['limits']['max_packages'])) / {{ $quota['limits']['max_packages'] }}@endif</dd></div>
        </dl>
    </div>

    <div class="rsl-panel mt-6">
        <div class="rsl-panel-head">
            <h2 class="rsl-panel-title">Transaction ledger</h2>
        </div>
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Wallet</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Direction</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td class="px-4 py-3">{{ $tx->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">{{ ucfirst($tx->wallet_type) }}</td>
                            <td class="px-4 py-3">{{ $tx->transaction_type }}</td>
                            <td class="px-4 py-3">{{ $tx->direction }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $tx->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $tx->balance_after, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">Ledger is empty.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
