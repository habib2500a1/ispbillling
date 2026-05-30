@extends('reseller.layout')

@section('title', 'Collect payment')

@section('content')
    <div class="rsl-card p-6 max-w-md">
        <h1 class="text-xl font-bold">Collect payment</h1>
        <p class="text-sm text-slate-600">{{ $customer->customer_code }} — {{ $customer->name }}</p>
        <p class="mt-2 text-lg font-bold text-rose-700">Due: {{ number_format($openDue, 2) }} BDT</p>

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

        <form method="post" action="{{ route('reseller.customers.collect.store', $customer) }}" class="mt-6 grid gap-4">
            @csrf
            <div><label class="block text-xs font-bold uppercase rsl-text-muted">Amount (BDT)</label><input type="number" name="amount" step="0.01" min="0" required class="rsl-input mt-1" value="{{ number_format($openDue, 2, '.', '') }}"></div>
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
@endsection
