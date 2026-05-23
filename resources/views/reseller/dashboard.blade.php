@extends('reseller.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-2xl font-bold text-slate-900">Welcome, {{ $reseller->name }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $reseller->franchiseTypeLabel() }} · Commission: {{ $reseller->commissionLabel() }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($reseller->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_VIEW))
                <a href="{{ route('reseller.customers.index') }}" class="rsl-btn-sm">Subscribers</a>
            @endif
            @if ($reseller->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW))
                <a href="{{ route('reseller.commissions.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Commissions</a>
            @endif
            @if ($reseller->canPortal(\App\Support\ResellerPortalPermission::SETTLEMENT_MANAGE))
                <a href="{{ route('reseller.settlements.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Settlements</a>
            @endif
        </div>
    </div>

    <div class="rsl-kpi-grid mt-6">
        <div class="rsl-metric">
            <p class="rsl-metric-label">Subscribers</p>
            <p class="rsl-metric-value text-indigo-700">{{ $metrics['customers_total'] }}</p>
            <p class="rsl-metric-sub">{{ $metrics['customers_active'] }} active · {{ $metrics['customers_online'] }} online</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Today collection</p>
            <p class="rsl-metric-value text-emerald-700">{{ number_format($metrics['today_collection'], 0) }} <span class="text-base">BDT</span></p>
            <p class="rsl-metric-sub">Month: {{ number_format($metrics['month_collection'], 0) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Wallet</p>
            <p class="rsl-metric-value text-sky-700">{{ number_format($metrics['wallet'], 0) }} <span class="text-base">BDT</span></p>
            <p class="rsl-metric-sub">Outstanding {{ number_format($metrics['outstanding_balance'], 0) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Pending commission</p>
            <p class="rsl-metric-value text-amber-700">{{ number_format($metrics['pending_commission'], 0) }} <span class="text-base">BDT</span></p>
            <p class="rsl-metric-sub">Paid this month {{ number_format($metrics['paid_commission_month'], 0) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Due subscribers</p>
            <p class="rsl-metric-value text-rose-700">{{ $metrics['due_customers'] }}</p>
            <p class="rsl-metric-sub">{{ $metrics['pending_settlements'] > 0 ? number_format($metrics['pending_settlements'], 0).' BDT pending settlement' : 'No pending settlement' }}</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Network</p>
            <p class="rsl-metric-value text-violet-700">{{ $metrics['onu_online'] }} ONU</p>
            <p class="rsl-metric-sub">{{ $metrics['weak_signal_onu'] }} weak signal · {{ $metrics['open_tickets'] }} tickets</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Sub-partners</p>
            <p class="rsl-metric-value">{{ $metrics['sub_resellers'] }}</p>
            <p class="rsl-metric-sub">{{ $metrics['customers_offline'] }} active offline</p>
        </div>
    </div>

    @if ($reseller->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW))
        <div class="rsl-card mt-8 overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Recent commissions</h2>
                <a href="{{ route('reseller.commissions.index') }}" class="text-sm font-semibold text-indigo-600 hover:underline">View all</a>
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
        </div>
    @endif
@endsection
