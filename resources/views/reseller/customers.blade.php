@extends('reseller.layout')

@section('title', 'Subscribers')

@section('content')
    <div class="rsl-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Your subscribers</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $customers->total() }} assigned to {{ $reseller->code }}</p>
            </div>
            <form method="get" class="flex gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name, code, phone" class="rsl-input max-w-xs">
                <button type="submit" class="rsl-btn">Search</button>
            </form>
        </div>
    </div>

    <div class="rsl-card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Package</th>
                        <th class="px-4 py-3">Zone</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-mono text-xs">{{ $customer->customer_code }}</td>
                            <td class="px-4 py-3 font-medium">{{ $customer->name }}</td>
                            <td class="px-4 py-3">{{ $customer->phone ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $customer->package?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $customer->zone?->name ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ $customer->status }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $customer->account_balance, 2) }} BDT</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No subscribers assigned yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $customers->links() }}</div>
        @endif
    </div>
@endsection
