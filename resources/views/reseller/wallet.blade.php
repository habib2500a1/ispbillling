@extends('reseller.layout')

@section('title', 'Wallet')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-xl font-bold text-slate-900">Wallet</h1>
        <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format((float) $reseller->wallet_balance, 2) }} BDT</p>
        <p class="mt-1 text-sm text-slate-500">Commission payouts and admin credits appear below.</p>
    </div>

    <div class="rsl-card mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="font-semibold text-slate-900">Recent transactions</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $t)
                        @php
                            $incoming = (int) $t->to_reseller_id === (int) $reseller->id;
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3">{{ $t->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $t->transfer_type) }}</td>
                            <td class="px-4 py-3 font-semibold {{ $incoming ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $incoming ? '+' : '−' }}{{ number_format((float) $t->amount, 2) }} BDT
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $t->reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">No wallet activity yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
