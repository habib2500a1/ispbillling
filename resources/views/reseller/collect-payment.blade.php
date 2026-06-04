@extends('reseller.layout')

@section('title', 'Collection')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Record payment',
        'subtitle' => $customer->customer_code.' — '.$customer->name,
        'backUrl' => route('reseller.customers.show', $customer),
        'backLabel' => '← Customers',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:32rem">
        <div class="rsl-callout rsl-callout--due mb-4">
            <strong>Due: {{ number_format($openDue, 2) }} BDT</strong>
            @if (($walletBalance ?? 0) > 0)
                <br><span class="text-sm">Wallet credit: {{ number_format($walletBalance, 2) }} BDT</span>
            @endif
        </div>

        @if ($personalMfs && ($personalMfs['bkash'] || $personalMfs['nagad']))
            <div class="rsl-callout rsl-callout--info mb-4">
                <p class="font-semibold">Personal MFS</p>
                @if ($personalMfs['bkash'])
                    <p class="mt-1">bKash: <span class="font-mono font-bold">{{ $personalMfs['bkash_number'] }}</span></p>
                @endif
                @if ($personalMfs['nagad'])
                    <p class="mt-1">Nagad: <span class="font-mono font-bold">{{ $personalMfs['nagad_number'] }}</span></p>
                @endif
                <p class="mt-2 text-xs">If the customer paid online, enter TrxID below.</p>
            </div>
        @endif

        <form method="post" action="{{ route('reseller.customers.collect.store', $customer) }}" class="rsl-form-grid" id="collect-form">
            @csrf

            @if ($fifoEnabled && $openInvoices->isNotEmpty())
                <div class="rsl-field">
                    <label class="rsl-field-label" for="allocation_mode">Apply payment to</label>
                    <select name="allocation_mode" id="allocation_mode" class="rsl-input">
                        <option value="fifo">Oldest bills first (FIFO) — {{ $openInvoices->count() }} open</option>
                        <option value="single">Single invoice</option>
                    </select>
                </div>
                <div class="rsl-field hidden" id="invoice-picker">
                    <label class="rsl-field-label">Invoice</label>
                    <select name="invoice_id" class="rsl-input">
                        @foreach ($openInvoices as $inv)
                            <option value="{{ $inv['id'] }}">
                                {{ $inv['number'] }} — due {{ number_format($inv['due'], 2) }} BDT
                                @if ($inv['due_date']) ({{ $inv['due_date'] }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="rsl-callout text-xs">
                    <p class="font-semibold mb-2">Open invoices</p>
                    <ul class="space-y-1 pl-4 list-disc">
                        @foreach ($openInvoices as $inv)
                            <li>{{ $inv['number'] }}: {{ number_format($inv['due'], 2) }} BDT</li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($openInvoices->isEmpty())
                <input type="hidden" name="allocation_mode" value="advance">
                <p class="rsl-field-hint">No open bills — amount will go to wallet.</p>
            @endif

            <div class="rsl-field">
                <label class="rsl-field-label">Amount (BDT)</label>
                <input type="number" name="amount" step="0.01" min="0" required class="rsl-input"
                    value="{{ number_format($openDue > 0 ? $openDue : 0, 2, '.', '') }}">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label">Method</label>
                <select name="method" class="rsl-input">
                    @foreach ($paymentMethods as $val => $label)
                        <option value="{{ $val }}" @selected($val === 'cash')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label">Reference / TrxID</label>
                <input name="reference" class="rsl-input" placeholder="Optional">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label">Notes</label>
                <input name="notes" class="rsl-input">
            </div>
            <button type="submit" class="rsl-btn" style="width:100%">Record payment</button>
        </form>
    </div>

    @if ($fifoEnabled && $openInvoices->isNotEmpty())
        <script>
            (function () {
                const mode = document.getElementById('allocation_mode');
                const picker = document.getElementById('invoice-picker');
                if (!mode || !picker) return;
                const sync = () => picker.classList.toggle('hidden', mode.value !== 'single');
                mode.addEventListener('change', sync);
                sync();
            })();
        </script>
    @endif
@endsection
