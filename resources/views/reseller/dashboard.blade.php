@extends('reseller.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-2xl font-bold text-slate-900">Welcome, {{ $reseller->name }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $reseller->franchiseTypeLabel() }} · Commission: {{ $reseller->commissionLabel() }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('reseller.customers.index') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">Subscribers</a>
            <a href="{{ route('reseller.commissions.index') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700">Commissions</a>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rsl-metric">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Subscribers</p>
            <p class="mt-2 text-3xl font-bold text-indigo-700">{{ $stats['customers'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $activeCustomers }} active · {{ $onlineCustomers ?? 0 }} online</p>
        </div>
        <div class="rsl-metric">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Wallet</p>
            <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($stats['wallet'], 0) }} <span class="text-lg">BDT</span></p>
        </div>
        <div class="rsl-metric">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pending commission</p>
            <p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format($stats['pending_commission'], 0) }} <span class="text-lg">BDT</span></p>
        </div>
        <div class="rsl-metric">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sub-resellers</p>
            <p class="mt-2 text-3xl font-bold text-violet-700">{{ $stats['sub_resellers'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $overdueInvoices }} with open bills</p>
        </div>
    </div>

    <div class="rsl-card mt-8 overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="font-semibold text-slate-900">Recent commissions</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Subscriber</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3">Commission</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentCommissions as $row)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3">{{ $row->earned_at?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row->customer?->name ?? '—' }}<br><span class="text-xs text-slate-500">{{ $row->customer?->customer_code }}</span></td>
                            <td class="px-4 py-3">{{ number_format((float) ($row->payment?->amount ?? $row->gross_amount), 2) }} BDT</td>
                            <td class="px-4 py-3 font-semibold text-emerald-700">{{ number_format((float) $row->commission_amount, 2) }} BDT</td>
                            <td class="px-4 py-3 capitalize">{{ $row->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No commissions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 text-right">
            <a href="{{ route('reseller.commissions.index') }}" class="text-sm font-semibold text-indigo-600 hover:underline">View all commissions →</a>
        </div>
    </div>
@endsection
