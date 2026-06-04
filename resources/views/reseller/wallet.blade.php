@extends('reseller.layout')

@section('title', 'Wallet')

@section('content')
    <div class="rsl-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="rsl-title">Wallet</h1>
                <p class="rsl-subtitle">Main balance, bonus wallet, and top-up</p>
            </div>
            <a href="{{ route('reseller.wallet.overview') }}" class="rsl-btn-sm rsl-btn-sm--outline">Full ledger & quotas →</a>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                <p class="text-xs font-bold uppercase text-emerald-800 dark:text-emerald-300">Main</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ number_format((float) $reseller->wallet_balance, 2) }} BDT</p>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50/80 p-4 dark:border-sky-800 dark:bg-sky-950/30">
                <p class="text-xs font-bold uppercase text-sky-800 dark:text-sky-300">Bonus</p>
                <p class="mt-1 text-2xl font-bold text-sky-700 dark:text-sky-400">{{ number_format((float) $reseller->bonus_wallet_balance, 2) }} BDT</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50/80 p-4 dark:border-violet-800 dark:bg-violet-950/30">
                <p class="text-xs font-bold uppercase text-violet-800 dark:text-violet-300">Credit limit</p>
                <p class="mt-1 text-2xl font-bold text-violet-700 dark:text-violet-400">{{ number_format((float) $reseller->credit_limit, 2) }} BDT</p>
            </div>
        </div>
        @if ($walletFrozen)
            <p class="mt-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">Wallet is frozen. Contact admin for settlement or withdrawal.</p>
        @else
            <p class="mt-1 rsl-subtitle">Commission payouts, top-ups and admin credits appear in the statement below.</p>
        @endif
    </div>

    @if ($rechargeEnabled && ($manualRechargeEnabled || $pipraPayEnabled))
        <div class="rsl-card mt-6 p-6">
            <h2 class="rsl-heading">Top up wallet</h2>
            <p class="rsl-subtitle mt-1">Add balance for collections and settlements. Min {{ number_format($rechargeLimits['min'], 0) }} · Max {{ number_format($rechargeLimits['max'], 0) }} BDT.</p>

            @if ($pipraPayEnabled)
                <form method="post" action="{{ route('reseller.wallet.piprapay') }}" class="mt-4 flex flex-wrap items-end gap-2 border-b border-slate-200 pb-6">
                    @csrf
                    <div>
                        <label class="text-xs rsl-text-muted">Online (PipraPay)</label>
                        <input type="number" name="amount" min="{{ $rechargeLimits['min'] }}" max="{{ $rechargeLimits['max'] }}" step="0.01" required class="rsl-input mt-1 w-40" placeholder="Amount">
                    </div>
                    <button type="submit" class="rsl-btn-sm">Pay online</button>
                </form>
            @endif

            @if ($manualRechargeEnabled)
                <form method="post" action="{{ route('reseller.wallet.recharge') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="text-xs rsl-text-muted">Amount (BDT)</label>
                        <input type="number" name="amount" min="{{ $rechargeLimits['min'] }}" max="{{ $rechargeLimits['max'] }}" step="0.01" required class="rsl-input mt-1 w-full">
                    </div>
                    <div>
                        <label class="text-xs rsl-text-muted">Payment method</label>
                        <select name="payment_method" required class="rsl-input mt-1 w-full">
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs rsl-text-muted">Transaction ID / reference</label>
                        <input type="text" name="reference" required maxlength="128" class="rsl-input mt-1 w-full" placeholder="bKash TrxID">
                    </div>
                    <div>
                        <label class="text-xs rsl-text-muted">Notes (optional)</label>
                        <input type="text" name="notes" maxlength="1000" class="rsl-input mt-1 w-full" placeholder="Sender number, bank branch…">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rsl-btn-sm">Submit for approval</button>
                        <p class="mt-2 text-xs rsl-text-muted">Send payment to your ISP’s official number/account, then submit the TrxID here. Wallet credits after admin verification.</p>
                    </div>
                </form>
            @endif
        </div>

        @if ($rechargeRequests->isNotEmpty())
            <div class="rsl-card mt-6 overflow-hidden">
                <div class="rsl-card-header"><h2 class="rsl-heading">Top-up requests</h2></div>
                <div class="overflow-x-auto">
                    <table class="rsl-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Reference</th>
                                <th class="px-4 py-3 text-left">Method</th>
                                <th class="px-4 py-3 text-left">Amount</th>
                                <th class="px-4 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rechargeRequests as $row)
                                <tr>
                                    <td class="px-4 py-3 rsl-text">{{ $row->created_at?->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-3 rsl-text-muted">{{ $row->request_number }}</td>
                                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $row->payment_method) }}</td>
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

    <div class="rsl-card mt-6 overflow-hidden">
        <div class="rsl-card-header">
            <h2 class="rsl-heading">Wallet statement</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
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
                            $credit = (int) $t->to_reseller_id === (int) $reseller->id && (int) $t->from_reseller_id !== (int) $reseller->id;
                            $debit = (int) $t->from_reseller_id === (int) $reseller->id
                                && in_array($t->transfer_type, ['debit', 'wholesale_debit'], true);
                            $incoming = (int) $t->to_reseller_id === (int) $reseller->id;
                        @endphp
                        <tr>
                            <td class="px-4 py-3 rsl-text">{{ $t->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">{{ \App\Models\ResellerBalanceTransfer::typeLabel($t->transfer_type) }}</td>
                            <td class="px-4 py-3 font-semibold text-emerald-700">{{ $incoming && ! $debit ? number_format((float) $t->amount, 2) : '—' }}</td>
                            <td class="px-4 py-3 font-semibold text-rose-700">{{ $debit ? number_format((float) $t->amount, 2) : '—' }}</td>
                            <td class="px-4 py-3 rsl-text-muted">{{ $t->reference ?? '—' }}</td>
                            <td class="px-4 py-3 rsl-text-muted">{{ Str::limit($t->notes, 40) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center rsl-text-muted">No wallet activity yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
