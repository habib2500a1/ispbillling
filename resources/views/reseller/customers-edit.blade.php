@extends('reseller.layout')

@section('title', 'Edit subscriber')

@section('content')
    <div class="rsl-card p-6 max-w-2xl">
        <h1 class="text-xl font-bold">Edit {{ $customer->name }}</h1>
        <form method="post" action="{{ route('reseller.customers.update', $customer) }}" class="mt-6 grid gap-4">
            @csrf
            @method('PUT')
            <div><label class="block text-xs font-bold uppercase text-slate-500">Name</label><input name="name" value="{{ $customer->name }}" required class="mt-1 w-full rounded-lg border px-3 py-2"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500">Phone</label><input name="phone" value="{{ $customer->phone }}" required class="mt-1 w-full rounded-lg border px-3 py-2"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500">Address</label><input name="address" value="{{ $customer->address }}" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border px-3 py-2">
                    @foreach ($options['status_options'] as $val => $label)
                        <option value="{{ $val }}" @selected($customer->status === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rsl-btn">Save changes</button>
        </form>
    </div>
@endsection
