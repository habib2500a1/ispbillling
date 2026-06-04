@extends('reseller.layout')

@section('title', 'Collect payment')

@section('content')
    <div class="rsl-card p-6 max-w-lg">
        <h1 class="text-xl font-bold">Collect payment</h1>
        <p class="text-sm text-slate-600">{{ $customer->customer_code }} — {{ $customer->name }}</p>
        <p class="mt-2 text-lg font-bold text-rose-700">Open due: {{ number_format($openDue, 2) }} BDT</p>
        @if (($walletBalance ?? 0) > 0)
            <p class="text-sm text-emerald-700 mt-1">Wallet credit: {{ number_format($walletBalance, 2) }} BDT</p>
        @endif

        @if ($personalMfs && ($personalMfs['bkash'] || $personalMfs['nagad']))
            <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm">
                <p class="font-semibold text-sky-900">Your personal MFS numbers</p>
                @if ($personalMfs['bkash'])
                    <p class="mt-1">bKash: <span class="font-mono font-bold">{{ $personalMfs['bkash_number'] }}</span></p>
                @endif
                @if ($personalMfs['nagad'])
                    <p class="mt-1">Nagad: <span class="font-mono font-bold">{{ $personalMfs['nagad_number'] }}</span></p>
                @endif
                <p class="mt-2 text-xs text-sky-800">Customer pays here, then you record the TrxID below.</p>
            </div>
        @endif

        <form method="post" action="{{ route('reseller.customers.collect.store', $customer) }}" class="mt-6 grid gap-4" id="collect-form">
            @csrf

            @if ($fifoEnabled && $openInvoices->isNotEmpty())
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Apply payment to</label>
                    <select name="allocation_mode" id="allocation_mode" class="rsl-input mt-1">
                        <option value="fifo">Oldest bills first (FIFO) — {{ $openInvoices->count() }} open</option>
                        <option value="single">Single invoice</option>
                    </select>
                </div>
                <div id="invoice-picker" class="hidden">
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Invoice</label>
                    <select name="invoice_id" class="rsl-input mt-1">
                        @foreach ($openInvoices as $inv)
                            <option value="{{ $inv['id'] }}">
                                {{ $inv['number'] }} — due {{ number_format($inv['due'], 2) }} BDT
                                @if ($inv['due_date']) ({{ $inv['due_date'] }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                    <p class="font-semibold text-slate-800 mb-2">Open invoices</p>
                    <ul class="space-y-1">
                        @foreach ($openInvoices as $inv)
                            <li>{{ $inv['number'] }}: {{ number_format($inv['due'], 2) }} BDT</li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($openInvoices->isEmpty())
                <input type="hidden" name="allocation_mode" value="advance">
                <p class="text-sm text-slate-600">No open bills — amount will be credited to subscriber wallet.</p>
            @endif

            <div>
                <label class="block text-xs font-bold uppercase rsl-text-muted">Amount (BDT)</label>
                <input type="number" name="amount" step="0.01" min="0" required class="rsl-input mt-1" value="{{ number_format($openDue > 0 ? $openDue : 0, 2, '.', '') }}">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase rsl-text-muted">Method</label>
                <select name="method" class="rsl-input mt-1">
                    @foreach ($paymentMethods as $val => $label)
                        <option value="{{ $val }}" @selected($val === 'cash')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="block text-xs font-bold uppercase rsl-text-muted">Reference / TrxID</label><input name="reference" class="rsl-input mt-1" placeholder="Optional"></div>
            <div><label class="block text-xs font-bold uppercase rsl-text-muted">Notes</label><input name="notes" class="rsl-input mt-1"></div>
            <button type="submit" class="rsl-btn w-full">Record payment</button>
        </form>
    </div>

    @if ($fifoEnabled && $openInvoices->isNotEmpty())
        <script>
            (function () {
                const mode = document.getElementById('allocation_mode');
                const picker = document.getElementById('invoice-picker');
                if (!mode || !picker) return;
                const sync = () => {
                    picker.classList.toggle('hidden', mode.value !== 'single');
                };
                mode.addEventListener('change', sync);
                sync();
            })();
        </script>
    @endif
@endsection
