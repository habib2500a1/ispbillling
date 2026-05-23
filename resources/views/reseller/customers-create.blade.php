@extends('reseller.layout')

@section('title', 'New subscriber')

@section('content')
    <div class="rsl-card p-6 max-w-2xl">
        <h1 class="text-xl font-bold">New subscriber</h1>
        <form method="post" action="{{ route('reseller.customers.store') }}" class="mt-6 grid gap-4">
            @csrf
            <div><label class="block text-xs font-bold uppercase text-slate-500">Name</label><input name="name" required class="mt-1 w-full rounded-lg border px-3 py-2"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500">Phone</label><input name="phone" required class="mt-1 w-full rounded-lg border px-3 py-2"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500">Address</label><input name="address" required class="mt-1 w-full rounded-lg border px-3 py-2"></div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Package</label>
                <select name="package_id" required class="mt-1 w-full rounded-lg border px-3 py-2">
                    @foreach ($options['packages'] as $pkg)
                        <option value="{{ $pkg['id'] }}">{{ $pkg['name'] }} — {{ number_format((float) ($pkg['selling_price'] ?? $pkg['price_monthly']), 0) }} BDT</option>
                    @endforeach
                </select>
            </div>
            <div><label class="block text-xs font-bold uppercase text-slate-500">Billing mode</label>
                <select name="billing_mode" class="mt-1 w-full rounded-lg border px-3 py-2">
                    @foreach ($options['billing_modes'] as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rsl-btn w-full sm:w-auto">Create subscriber</button>
        </form>
    </div>
@endsection
