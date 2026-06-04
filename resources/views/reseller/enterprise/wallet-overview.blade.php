@extends('reseller.layout')

@section('title', 'Wallet overview')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Wallet overview</h1>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rsl-metric">
                <p class="rsl-metric-label">Main wallet</p>
                <p class="rsl-metric-value text-emerald-700">{{ number_format((float) $reseller->wallet_balance, 2) }}</p>
            </div>
            <div class="rsl-metric">
                <p class="rsl-metric-label">Bonus wallet</p>
                <p class="rsl-metric-value text-sky-700">{{ number_format((float) $reseller->bonus_wallet_balance, 2) }}</p>
            </div>
            <div class="rsl-metric">
                <p class="rsl-metric-label">Credit limit</p>
                <p class="rsl-metric-value">{{ number_format((float) $reseller->credit_limit, 2) }}</p>
            </div>
            <div class="rsl-metric">
                <p class="rsl-metric-label">Available (main + credit)</p>
                <p class="rsl-metric-value">{{ number_format($availableMain, 2) }}</p>
            </div>
        </div>
        @if ($isLowBalance)
            <p class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">Low balance — recharge soon to avoid auto-suspend.</p>
        @endif
    </div>

    <div class="rsl-card mt-6 p-6">
        <h2 class="rsl-heading">Resource quotas</h2>
        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="rsl-text-muted">Customers</dt><dd>{{ $quota['customers'] }}@if (! empty($quota['limits']['max_clients'])) / {{ $quota['limits']['max_clients'] }}@endif</dd></div>
            <div><dt class="rsl-text-muted">Active</dt><dd>{{ $quota['active_customers'] }}@if (! empty($quota['limits']['max_active_clients'])) / {{ $quota['limits']['max_active_clients'] }}@endif</dd></div>
            <div><dt class="rsl-text-muted">ONU</dt><dd>{{ $quota['onu'] }}@if (! empty($quota['limits']['max_onu'])) / {{ $quota['limits']['max_onu'] }}@endif</dd></div>
            <div><dt class="rsl-text-muted">Packages</dt><dd>{{ $quota['packages'] }}@if (! empty($quota['limits']['max_packages'])) / {{ $quota['limits']['max_packages'] }}@endif</dd></div>
        </dl>
    </div>

    <div class="rsl-card mt-6 p-6">
        <h2 class="rsl-heading">Transaction ledger</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Wallet</th>
                        <th>Type</th>
                        <th>Direction</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Balance after</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td>{{ $tx->created_at->format('d M Y H:i') }}</td>
                            <td>{{ ucfirst($tx->wallet_type) }}</td>
                            <td>{{ $tx->transaction_type }}</td>
                            <td>{{ $tx->direction }}</td>
                            <td class="text-right">{{ number_format((float) $tx->amount, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $tx->balance_after, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="rsl-text-muted py-4">No ledger entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
