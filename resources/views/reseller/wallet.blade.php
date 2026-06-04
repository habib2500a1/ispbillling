@extends('reseller.layout')

@section('title', 'Wallet')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Wallet',
        'subtitle' => 'Main, bonus, and top-up.',
        'actionUrl' => route('reseller.wallet.overview'),
        'actionLabel' => 'Ledger & quotas →',
    ])

    <div class="rsl-metric-grid">
        <div class="rsl-metric">
            <p class="rsl-metric-label">Main</p>
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
    </div>

    @if ($walletFrozen)
        <div class="rsl-callout rsl-callout--due mt-4">Wallet frozen — contact admin for settlement or withdrawal.</div>
    @endif

    @if ($rechargeEnabled && ($manualRechargeEnabled || $pipraPayEnabled))
        <div class="rsl-panel rsl-panel-pad mt-6">
            <h2 class="rsl-panel-title">Top-up</h2>
            <p class="mt-1 text-sm" style="color:var(--rsl-text-muted)">Min {{ number_format($rechargeLimits['min'], 0) }} · Max {{ number_format($rechargeLimits['max'], 0) }} BDT</p>

            @if ($pipraPayEnabled)
                <form method="post" action="{{ route('reseller.wallet.piprapay') }}" class="rsl-form-grid rsl-form-grid--2 mt-4 pb-6" style="border-bottom:1px solid var(--rsl-border)">
                    @csrf
                    <div class="rsl-field">
                        <label class="rsl-field-label">Online (PipraPay)</label>
                        <input type="number" name="amount" min="{{ $rechargeLimits['min'] }}" max="{{ $rechargeLimits['max'] }}" step="0.01" required class="rsl-input" placeholder="Amount">
                    </div>
                    <div class="rsl-field" style="align-self:end">
                        <button type="submit" class="rsl-btn">Pay online</button>
                    </div>
                </form>
            @endif

            @if ($manualRechargeEnabled)
                <form method="post" action="{{ route('reseller.wallet.recharge') }}" class="rsl-form-grid rsl-form-grid--2 mt-4">
                    @csrf
                    <div class="rsl-field">
                        <label class="rsl-field-label">Amount (BDT)</label>
                        <input type="number" name="amount" min="{{ $rechargeLimits['min'] }}" max="{{ $rechargeLimits['max'] }}" step="0.01" required class="rsl-input">
                    </div>
                    <div class="rsl-field">
                        <label class="rsl-field-label">Payment method</label>
                        <select name="payment_method" required class="rsl-input">
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rsl-field">
                        <label class="rsl-field-label">TrxID / reference</label>
                        <input type="text" name="reference" required maxlength="128" class="rsl-input" placeholder="bKash TrxID">
                    </div>
                    <div class="rsl-field">
                        <label class="rsl-field-label">Notes</label>
                        <input type="text" name="notes" maxlength="1000" class="rsl-input" placeholder="Optional">
                    </div>
                    <div style="grid-column:1/-1">
                        <button type="submit" class="rsl-btn">Submit for approval</button>
                        <p class="mt-2 text-xs" style="color:var(--rsl-text-muted)">Pay the ISP official number, then submit TrxID. Credited after verification.</p>
                    </div>
                </form>
            @endif
        </div>

        @if ($rechargeRequests->isNotEmpty())
            <div class="rsl-panel mt-6">
                <div class="rsl-panel-head"><h2 class="rsl-panel-title">Top-up requests</h2></div>
                <div class="rsl-table-wrap">
                    <table class="rsl-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">Method</th>
                                <th class="px-4 py-3">Amount</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rechargeRequests as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row->created_at?->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->request_number }}</td>
                                    <td class="px-4 py-3">{{ str_replace('_', ' ', $row->payment_method) }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ number_format((float) $row->amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ $row->statusLabel() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    <div class="rsl-panel mt-6">
        <div class="rsl-panel-head"><h2 class="rsl-panel-title">Wallet statement</h2></div>
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Credit</th>
                        <th class="px-4 py-3">Debit</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $t)
                        @php
                            $debit = (int) $t->from_reseller_id === (int) $reseller->id
                                && in_array($t->transfer_type, ['debit', 'wholesale_debit'], true);
                            $incoming = (int) $t->to_reseller_id === (int) $reseller->id;
                        @endphp
                        <tr>
                            <td class="px-4 py-3">{{ $t->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">{{ \App\Models\ResellerBalanceTransfer::typeLabel($t->transfer_type) }}</td>
                            <td class="px-4 py-3 font-semibold" style="color:var(--rsl-teal-600)">{{ $incoming && ! $debit ? number_format((float) $t->amount, 2) : '—' }}</td>
                            <td class="px-4 py-3 font-semibold" style="color:var(--rsl-danger)">{{ $debit ? number_format((float) $t->amount, 2) : '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $t->reference ?? '—' }}</td>
                            <td class="px-4 py-3">{{ Str::limit($t->notes, 40) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
