@extends('reseller.layout')

@section('title', 'Dashboard')

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $collectionRate = min(100, max(0, (float) ($metrics['collection_rate'] ?? 0)));
    $creditLimit = (float) ($metrics['credit_limit'] ?? 0);
    $available = (float) ($metrics['available_balance'] ?? $metrics['wallet'] ?? 0);
    $creditUsedPct = $creditLimit > 0 ? min(100, round((($creditLimit - $available) / $creditLimit) * 100, 1)) : null;
    $dueUrl = $portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT)
        ? route('reseller.customers.index', ['due' => 1])
        : route('reseller.customers.index');
    $hqDueUrl = \Illuminate\Support\Facades\Route::has('reseller.due-account') ? route('reseller.due-account') : null;
    $walletUrl = \Illuminate\Support\Facades\Route::has('reseller.wallet.overview')
        ? route('reseller.wallet.overview')
        : route('reseller.wallet.index');
@endphp

@section('content')
<div class="rsl-pro-dash">
    <header class="rsl-pro-head">
        <div class="rsl-pro-head-copy">
            <p class="rsl-pro-greeting">{{ $greeting }} · {{ now()->translatedFormat('l, d M Y') }}</p>
            <h1 class="rsl-pro-title">{{ $reseller->name }}</h1>
            <p class="rsl-pro-meta">
                @if ($reseller->code)<span class="rsl-pro-code">{{ $reseller->code }}</span>@endif
                {{ $reseller->franchiseTypeLabel() }}
                @if ($portal->staff()) · {{ $portal->actorName() }}@endif
            </p>
        </div>
        <div class="rsl-pro-ring" aria-label="Collection {{ number_format($collectionRate, 0) }}%">
            <svg viewBox="0 0 36 36" class="rsl-pro-ring-svg">
                <path class="rsl-pro-ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <path class="rsl-pro-ring-fill" stroke-dasharray="{{ $collectionRate }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <div class="rsl-pro-ring-label">
                <span class="rsl-pro-ring-value">{{ number_format($collectionRate, 0) }}%</span>
                <span class="rsl-pro-ring-caption">Collection</span>
            </div>
        </div>
    </header>

    <div class="rsl-pro-actions">
        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
            <a href="{{ $dueUrl }}" class="rsl-pro-action rsl-pro-action--primary">
                <x-reseller-icon name="collect" class="rsl-icon-svg" />
                Due Collection
            </a>
        @endif
        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_CREATE))
            <a href="{{ route('reseller.customers.create') }}" class="rsl-pro-action">
                <x-reseller-icon name="plus" class="rsl-icon-svg" />
                New subscriber
            </a>
        @endif
        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_VIEW))
            <a href="{{ route('reseller.customers.index') }}" class="rsl-pro-action">
                <x-reseller-icon name="users" class="rsl-icon-svg" />
                List
            </a>
        @endif
        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE) && \Illuminate\Support\Facades\Route::has('reseller.invoices.index'))
            <a href="{{ route('reseller.invoices.index') }}" class="rsl-pro-action">
                <x-reseller-icon name="invoice" class="rsl-icon-svg" />
                Bills
            </a>
        @endif
    </div>

    <section class="rsl-pro-bento" aria-label="Key figures">
        <a href="{{ $dueUrl }}" class="rsl-pro-tile rsl-pro-tile--due rsl-pro-tile--span2">
            <span class="rsl-pro-tile-label">Customer due</span>
            <span class="rsl-pro-tile-value">{{ number_format($metrics['due_amount'], 0) }} <small> BDT</small></span>
            <span class="rsl-pro-tile-sub">{{ $metrics['due_customers'] }} subscribers with due bills</span>
        </a>
        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::WALLET_VIEW))
            <a href="{{ $walletUrl }}" class="rsl-pro-tile rsl-pro-tile--wallet">
                <span class="rsl-pro-tile-label">Wallet</span>
                <span class="rsl-pro-tile-value">{{ number_format($metrics['wallet'], 0) }} <small> BDT</small></span>
                <span class="rsl-pro-tile-sub">Available {{ number_format($available, 0) }} BDT</span>
            </a>
        @endif
        <article class="rsl-pro-tile rsl-pro-tile--today">
            <span class="rsl-pro-tile-label">Today's collection</span>
            <span class="rsl-pro-tile-value">{{ number_format($metrics['today_collection'], 0) }} <small> BDT</small></span>
            <span class="rsl-pro-tile-sub">{{ $metrics['today_collection_count'] }} payments</span>
        </article>
        <article class="rsl-pro-tile rsl-pro-tile--month">
            <span class="rsl-pro-tile-label">This month</span>
            <span class="rsl-pro-tile-value">{{ number_format($metrics['month_collection'], 0) }} <small> BDT</small></span>
            <span class="rsl-pro-tile-sub">{{ $metrics['customers_total'] }} total subscribers</span>
        </article>
        @if ($hqDueUrl)
            <a href="{{ $hqDueUrl }}" class="rsl-pro-tile rsl-pro-tile--hq">
                <span class="rsl-pro-tile-label">HQ due</span>
                <span class="rsl-pro-tile-value">{{ number_format($metrics['admin_receivable_due'] ?? 0, 0) }} <small> BDT</small></span>
                <span class="rsl-pro-tile-sub">Wholesale ledger</span>
            </a>
        @endif
        <article class="rsl-pro-tile rsl-pro-tile--subs">
            <span class="rsl-pro-tile-label">Active / total</span>
            <span class="rsl-pro-tile-value">{{ $metrics['customers_active'] }} <small>/ {{ $metrics['customers_total'] }}</small></span>
            <span class="rsl-pro-tile-sub">Suspended {{ $metrics['customers_suspended'] ?? 0 }} · ONU {{ $metrics['onu_online'] }}</span>
        </article>
    </section>

    @if (!empty($metrics['alerts']))
        <section class="rsl-pro-panel">
            <h2 class="rsl-pro-panel-title">Alerts</h2>
            <div class="rsl-pro-alerts">
                @foreach ($metrics['alerts'] as $alert)
                    <article class="rsl-pro-alert rsl-pro-alert--{{ $alert['tone'] ?? 'info' }}">
                        <p class="rsl-pro-alert-title">{{ $alert['title'] }}</p>
                        <p class="rsl-pro-alert-value">{{ $alert['value'] }}</p>
                        <p class="rsl-pro-alert-hint">{{ $alert['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rsl-pro-stats" aria-label="Operations">
        <div class="rsl-pro-stat"><span class="rsl-pro-stat-val">{{ number_format($metrics['pending_commission'], 0) }}</span><span class="rsl-pro-stat-lbl">Pending commission</span></div>
        <div class="rsl-pro-stat"><span class="rsl-pro-stat-val">{{ $metrics['customers_new_month'] ?? 0 }}</span><span class="rsl-pro-stat-lbl">New (month)</span></div>
        <div class="rsl-pro-stat"><span class="rsl-pro-stat-val">{{ number_format($collectionRate, 0) }}%</span><span class="rsl-pro-stat-lbl">Collection rate</span></div>
        @if ($creditUsedPct !== null)
            <div class="rsl-pro-stat"><span class="rsl-pro-stat-val">{{ $creditUsedPct }}%</span><span class="rsl-pro-stat-lbl">Credit used</span></div>
        @endif
    </section>

    @if (!empty($chartData))
        <section class="rsl-pro-panel">
            <h2 class="rsl-pro-panel-title">30-day trend</h2>
            <div class="rsl-pro-charts">
                @foreach (['collection' => 'Collection', 'revenue' => 'Commission', 'growth' => 'Subscribers'] as $key => $chartTitle)
                    <div class="rsl-pro-chart-box">
                        <h3 class="rsl-pro-chart-label">{{ $chartTitle }}</h3>
                        <div class="rsl-pro-chart-canvas"><canvas id="chart-{{ $key }}"></canvas></div>
                    </div>
                @endforeach
            </div>
        </section>
        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const data = @json($chartData);
                const styles = { collection: { type: 'bar', color: '#14b8a6' }, revenue: { type: 'bar', color: '#8b5cf6' }, growth: { type: 'line', color: '#3b82f6' } };
                Object.keys(data).forEach(function (key) {
                    const el = document.getElementById('chart-' + key);
                    if (!el || typeof Chart === 'undefined') return;
                    const cfg = styles[key] || styles.collection;
                    const c = cfg.color;
                    new Chart(el, {
                        type: cfg.type,
                        data: { labels: data[key].labels, datasets: [{ data: data[key].values, backgroundColor: cfg.type === 'line' ? c + '22' : c + 'bb', borderColor: c, borderWidth: 2, fill: cfg.type === 'line', tension: 0.4, pointRadius: 0, borderRadius: cfg.type === 'bar' ? 6 : 0 }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 6, font: { size: 10 } } }, y: { beginAtZero: true, ticks: { font: { size: 10 } } } } }
                    });
                });
            })();
        </script>
        @endpush
    @endif

    <div class="rsl-pro-panels">
        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
            <section class="rsl-pro-panel">
                <div class="rsl-pro-panel-header">
                    <h2 class="rsl-pro-panel-title">Recent collections</h2>
                    <a href="{{ route('reseller.customers.index') }}" class="rsl-pro-panel-link">All</a>
                </div>
                <ul class="rsl-pro-feed rsl-pro-panel-body">
                    @forelse (($recentPayments ?? collect())->take(6) as $pay)
                        @php
                            $name = $pay->customer?->name ?? 'Subscribers';
                            $ini = collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                        @endphp
                        <li>
                            <a href="{{ route('reseller.payments.receipt', $pay) }}" class="rsl-pro-feed-item" target="_blank" rel="noopener">
                                <span class="rsl-pro-feed-icon">{{ $ini ?: '?' }}</span>
                                <span class="rsl-pro-feed-body">
                                    <span class="rsl-pro-feed-title">{{ $name }}</span>
                                    <span class="rsl-pro-feed-meta">{{ $pay->paid_at?->format('d M · H:i') }}</span>
                                </span>
                                <span class="rsl-pro-feed-amount">+{{ number_format((float) $pay->amount, 0) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="rsl-pro-feed-empty">No collections yet.</li>
                    @endforelse
                </ul>
            </section>
        @endif

        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW))
            <section class="rsl-pro-panel">
                <div class="rsl-pro-panel-header">
                    <h2 class="rsl-pro-panel-title">Commission</h2>
                    <a href="{{ route('reseller.commissions.index') }}" class="rsl-pro-panel-link">All</a>
                </div>
                <ul class="rsl-pro-feed rsl-pro-panel-body">
                    @forelse ($recentCommissions->take(6) as $row)
                        @php
                            $cName = $row->customer?->name ?? '—';
                            $cIni = collect(explode(' ', $cName))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                        @endphp
                        <li class="rsl-pro-feed-item">
                            <span class="rsl-pro-feed-icon">{{ $cIni ?: '?' }}</span>
                            <span class="rsl-pro-feed-body">
                                <span class="rsl-pro-feed-title">{{ $cName }}</span>
                                <span class="rsl-pro-feed-meta">{{ $row->earned_at?->format('d M') ?? '—' }} · {{ ucfirst($row->status) }}</span>
                            </span>
                            <span class="rsl-pro-feed-amount">+{{ number_format((float) $row->commission_amount, 0) }}</span>
                        </li>
                    @empty
                        <li class="rsl-pro-feed-empty">No commissions yet.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
</div>
@endsection
