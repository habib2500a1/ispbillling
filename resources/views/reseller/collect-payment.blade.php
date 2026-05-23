@extends('reseller.layout')

@section('title', 'Collect payment')

@section('content')
    <div class="rsl-card p-6 max-w-md">
        <h1 class="text-xl font-bold">Collect payment</h1>
        <p class="text-sm text-slate-600">{{ $customer->customer_code }} — {{ $customer->name }}</p>
        <p class="mt-2 text-lg font-bold text-rose-700">Due: {{ number_format($openDue, 2) }} BDT</p>
        <form method="post" action="{{ route('reseller.customers.collect.store', $customer) }}" class="mt-6 grid gap-4">
            @csrf
            <div><label class="block text-xs font-bold uppercase text-slate-500">Amount (BDT)</label><input type="number" name="amount" step="0.01" min="0" required class="mt-1 w-full rounded-lg border px-3 py-2 text-base"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500">Method</label><input name="method" value="cash" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500">Reference</label><input name="reference" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
            <button type="submit" class="rsl-btn w-full">Record payment</button>
        </form>
    </div>
@endsection
