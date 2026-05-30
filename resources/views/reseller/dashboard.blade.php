@extends('reseller.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Welcome, {{ $reseller->name }}</h1>
        <p class="rsl-subtitle">{{ $reseller->franchiseTypeLabel() }} · Commission: {{ $reseller->commissionLabel() }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_VIEW))
                <a href="{{ route('reseller.customers.index') }}" class="rsl-btn-sm">Subscribers</a>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW))
                <a href="{{ route('reseller.commissions.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Commissions</a>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::SETTLEMENT_MANAGE))
                <a href="{{ route('reseller.settlements.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Settlements</a>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT) && $metrics['customers_total'] > 0)
                <a href="{{ route('reseller.customers.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Collect payment</a>
            @endif
        </div>
    </div>

    @if (!empty($metrics['alerts']))
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($metrics['alerts'] as $alert)
                <div class="rsl-card p-5 border {{ match($alert['tone']) { 'rose' => 'border-rose-200', 'amber' => 'border-amber-200', 'sky' => 'border-sky-200', default => 'border-violet-200' } }}">
                    <p class="text-xs font-semibold uppercase tracking-wide rsl-text-muted">{{ $alert['title'] }}</p>
                    <p class="mt-2 text-xl font-bold rsl-text">{{ $alert['value'] }}</p>
                    <p class="mt-1 text-sm rsl-text-muted">{{ $alert['hint'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="rsl-kpi-grid mt-6">
        <div class="rsl-metric">
            <p class="rsl-metric-label">Subscribers</p>
            <p class="rsl-metric-value text-indigo-700">{{ $metrics['customers_total'] }}</p>
            <p class="rsl-metric-sub">{{ $metrics['customers_active'] }} active · {{ $metrics['customers_expired'] ?? 0 }} expired · {{ $metrics['customers_suspended'] ?? 0 }} suspended</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Today collection</p>
            <p class="rsl-metric-value text-emerald-700">{{ number_format($metrics['today_collection'], 0) }} <span class="text-base">BDT</span></p>
            <p class="rsl-metric-sub">{{ $metrics['today_collection_count'] }} payment(s) today · Month {{ number_format($metrics['month_collection'], 0) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Wallet</p>
            <p class="rsl-metric-value text-sky-700">{{ number_format($metrics['wallet'], 0) }} <span class="text-base">BDT</span></p>
            <p class="rsl-metric-sub">Outstanding {{ number_format($metrics['outstanding_balance'], 0) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Pending commission</p>
            <p class="rsl-metric-value text-amber-700">{{ number_format($metrics['pending_commission'], 0) }} <span class="text-base">BDT</span></p>
            <p class="rsl-metric-sub">Total {{ number_format($metrics['total_commission'] ?? 0, 0) }} BDT · Paid month {{ number_format($metrics['paid_commission_month'], 0) }} BDT</p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Due subscribers</p>
            <p class="rsl-metric-value text-rose-700">{{ $metrics['due_customers'] }}</p>
            <p class="rsl-metric-sub">{{ number_format($metrics['due_amount'], 0) }} BDT due · {{ $metrics['pending_settlements'] > 0 ? number_format($metrics['pending_settlements'], 0).' BDT pending settlement' : 'No pending settlement' }}</p>
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
        <div class="rsl-metric">
            <p class="rsl-metric-label">Collection rate</p>
            <p class="rsl-metric-value text-teal-700">{{ number_format($metrics['collection_rate'], 1) }}<span class="text-base">%</span></p>
            <p class="rsl-metric-sub">{{ $metrics['month_collection_count'] }} payment(s) this month @if($metrics['recent_payment_at']) · Last {{ $metrics['recent_payment_at'] }} @endif</p>
        </div>
    </div>

    @if (!empty($chartData))
        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            @foreach (['collection' => 'Collection (30 days)', 'revenue' => 'Commission revenue', 'growth' => 'Client growth'] as $key => $title)
                <div class="rsl-card p-4">
                    <h3 class="rsl-heading text-sm mb-3">{{ $title }}</h3>
                    <canvas id="chart-{{ $key }}" height="160"></canvas>
                </div>
            @endforeach
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const data = @json($chartData);
                const colors = { collection: '#10b981', revenue: '#8b5cf6', growth: '#3b82f6' };
                Object.keys(data).forEach(key => {
                    const el = document.getElementById('chart-' + key);
                    if (!el) return;
                    new Chart(el, {
                        type: key === 'growth' ? 'line' : 'bar',
                        data: {
                            labels: data[key].labels,
                            datasets: [{ data: data[key].values, backgroundColor: colors[key] + '99', borderColor: colors[key], borderWidth: 2, fill: key === 'growth', tension: 0.35 }]
                        },
                        options: { plugins: { legend: { display: false } }, scales: { x: { ticks: { maxTicksLimit: 6 } }, y: { beginAtZero: true } } }
                    });
                });
            })();
        </script>
    @endif

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT) && isset($recentPayments) && $recentPayments->isNotEmpty())
        <div class="rsl-card mt-8 overflow-hidden">
            <div class="rsl-card-header">
                <h2 class="rsl-heading">Recent payments</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="rsl-table w-full text-left text-sm">
                    <thead><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Subscriber</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Method</th><th class="px-4 py-3"></th></tr></thead>
                    <tbody>
                        @foreach ($recentPayments as $pay)
                            <tr>
                                <td class="px-4 py-3 rsl-text">{{ $pay->paid_at?->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3 rsl-text">{{ $pay->customer?->name }}<br><span class="text-xs rsl-text-muted">{{ $pay->customer?->customer_code }}</span></td>
                                <td class="px-4 py-3 font-semibold text-emerald-700">{{ number_format((float) $pay->amount, 2) }} BDT</td>
                                <td class="px-4 py-3 capitalize rsl-text">{{ $pay->method }}</td>
                                <td class="px-4 py-3"><a href="{{ route('reseller.payments.receipt', $pay) }}" class="rsl-link" target="_blank">Receipt</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW))
        <div class="rsl-card mt-8 overflow-hidden">
            <div class="rsl-card-header">
                <h2 class="rsl-heading">Recent commissions</h2>
                <a href="{{ route('reseller.commissions.index') }}" class="rsl-link">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="rsl-table w-full text-left text-sm">
                    <thead>
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
                            <tr>
                                <td class="px-4 py-3 rsl-text">{{ $row->earned_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 rsl-text">{{ $row->customer?->name ?? '—' }}<br><span class="text-xs rsl-text-muted">{{ $row->customer?->customer_code }}</span></td>
                                <td class="px-4 py-3 rsl-text">{{ number_format((float) ($row->payment?->amount ?? $row->gross_amount), 2) }} BDT</td>
                                <td class="px-4 py-3 font-semibold text-emerald-700">{{ number_format((float) $row->commission_amount, 2) }} BDT</td>
                                <td class="px-4 py-3 capitalize rsl-text">{{ $row->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center rsl-text-muted">No commissions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
