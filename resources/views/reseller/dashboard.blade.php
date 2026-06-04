@extends('reseller.layout')

@section('title', 'Dashboard')

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $collectionRate = min(100, max(0, (float) ($metrics['collection_rate'] ?? 0)));
    $creditLimit = (float) ($metrics['credit_limit'] ?? 0);
    $available = (float) ($metrics['available_balance'] ?? $metrics['wallet'] ?? 0);
    $creditUsedPct = $creditLimit > 0
        ? min(100, round((($creditLimit - $available) / $creditLimit) * 100, 1))
        : null;
    $dueUrl = $portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT)
        ? route('reseller.customers.index', ['due' => 1])
        : route('reseller.customers.index');
    $hqDueUrl = \Illuminate\Support\Facades\Route::has('reseller.due-account')
        ? route('reseller.due-account')
        : null;
    $walletUrl = \Illuminate\Support\Facades\Route::has('reseller.wallet.overview')
        ? route('reseller.wallet.overview')
        : route('reseller.wallet.index');
@endphp

@section('content')
    <div class="rsl-dash" data-dashboard="pro-mobile">
        <section class="rsl-dash-hero">
            <div class="rsl-dash-hero-inner">
                <div class="rsl-dash-hero-top">
                    <div class="rsl-dash-hero-copy">
                        <p class="rsl-dash-greeting">{{ $greeting }} · Dashboard</p>
                        <h1 class="rsl-dash-title">{{ $reseller->name }}</h1>
                        <div class="rsl-dash-meta">
                            @if ($reseller->code)
                                <span class="rsl-dash-code">{{ $reseller->code }}</span>
                            @endif
                            <span class="rsl-dash-meta-text">{{ $reseller->franchiseTypeLabel() }}</span>
                            <span class="rsl-dash-meta-date rsl-only-desktop" aria-hidden="true">·</span>
                            <span class="rsl-dash-meta-date rsl-only-desktop">{{ now()->translatedFormat('d M Y') }}</span>
                        </div>
                    </div>
                    <div class="rsl-dash-ring rsl-only-desktop" aria-label="Collection rate {{ number_format($collectionRate, 0) }} percent">
                        <svg class="rsl-dash-ring-svg" viewBox="0 0 36 36" role="img">
                            <path class="rsl-dash-ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="rsl-dash-ring-fill" stroke-dasharray="{{ $collectionRate }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        </svg>
                        <div class="rsl-dash-ring-label">
                            <span class="rsl-dash-ring-value">{{ number_format($collectionRate, 0) }}%</span>
                            <span class="rsl-dash-ring-caption">Collection</span>
                        </div>
                    </div>
                </div>

                <div class="rsl-dash-hero-cta">
                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                        <a href="{{ $dueUrl }}" class="rsl-dash-cta rsl-dash-cta--primary">
                            <x-reseller-icon name="collect" class="rsl-dash-cta-icon" />
                            <span>বকেয়া আদায়</span>
                        </a>
                    @endif
                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_VIEW))
                        <a href="{{ route('reseller.customers.index') }}" class="rsl-dash-cta rsl-dash-cta--ghost">
                            <x-reseller-icon name="users" class="rsl-dash-cta-icon" />
                            <span>সাবস্ক্রাইবার</span>
                        </a>
                    @endif
                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE) && \Illuminate\Support\Facades\Route::has('reseller.invoices.index'))
                        <a href="{{ route('reseller.invoices.index') }}" class="rsl-dash-cta rsl-dash-cta--ghost">
                            <x-reseller-icon name="invoice" class="rsl-dash-cta-icon" />
                            <span>মাসিক বিল</span>
                        </a>
                    @endif
                    @if ($hqDueUrl && $portal->canPortal(\App\Support\ResellerPortalPermission::WALLET_VIEW))
                        <a href="{{ $hqDueUrl }}" class="rsl-dash-cta rsl-dash-cta--ghost">
                            <x-reseller-icon name="building" class="rsl-dash-cta-icon" />
                            <span>HQ due</span>
                        </a>
                    @endif
                </div>

                <div class="rsl-dash-hero-stats rsl-only-desktop">
                    <div class="rsl-dash-hero-stat">
                        <p class="rsl-dash-hero-stat-label">Subscribers</p>
                        <p class="rsl-dash-hero-stat-value">{{ $metrics['customers_total'] }}</p>
                    </div>
                    <div class="rsl-dash-hero-stat">
                        <p class="rsl-dash-hero-stat-label">Active</p>
                        <p class="rsl-dash-hero-stat-value">{{ $metrics['customers_active'] }}</p>
                    </div>
                    <div class="rsl-dash-hero-stat">
                        <p class="rsl-dash-hero-stat-label">Today</p>
                        <p class="rsl-dash-hero-stat-value">{{ number_format($metrics['today_collection'], 0) }}</p>
                    </div>
                    <div class="rsl-dash-hero-stat">
                        <p class="rsl-dash-hero-stat-label">Month</p>
                        <p class="rsl-dash-hero-stat-value">{{ number_format($metrics['month_collection'], 0) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rsl-dash-strip rsl-only-mobile" aria-label="Summary">
            <div class="rsl-dash-strip-item">
                <span class="rsl-dash-strip-value">{{ $metrics['customers_total'] }}</span>
                <span class="rsl-dash-strip-label">Subs</span>
            </div>
            <div class="rsl-dash-strip-item">
                <span class="rsl-dash-strip-value">{{ number_format($collectionRate, 0) }}%</span>
                <span class="rsl-dash-strip-label">Collection</span>
            </div>
            <div class="rsl-dash-strip-item">
                <span class="rsl-dash-strip-value">{{ number_format($metrics['today_collection'], 0) }}</span>
                <span class="rsl-dash-strip-label">Today</span>
            </div>
            <div class="rsl-dash-strip-item">
                <span class="rsl-dash-strip-value">{{ $metrics['customers_active'] }}</span>
                <span class="rsl-dash-strip-label">Active</span>
            </div>
        </section>

        <section class="rsl-dash-money rsl-only-mobile" aria-label="Key balances">
            <a href="{{ $dueUrl }}" class="rsl-dash-money-tile rsl-dash-money-tile--rose">
                <span class="rsl-dash-money-label">কাস্টমার বকেয়া</span>
                <span class="rsl-dash-money-value">{{ number_format($metrics['due_amount'], 0) }}</span>
                <span class="rsl-dash-money-unit">BDT · {{ $metrics['due_customers'] }} subs</span>
            </a>
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::WALLET_VIEW))
                <a href="{{ $walletUrl }}" class="rsl-dash-money-tile rsl-dash-money-tile--sky">
                    <span class="rsl-dash-money-label">ওয়ালেট</span>
                    <span class="rsl-dash-money-value">{{ number_format($metrics['wallet'], 0) }}</span>
                    <span class="rsl-dash-money-unit">BDT available</span>
                </a>
            @else
                <div class="rsl-dash-money-tile rsl-dash-money-tile--sky">
                    <span class="rsl-dash-money-label">Month collection</span>
                    <span class="rsl-dash-money-value">{{ number_format($metrics['month_collection'], 0) }}</span>
                    <span class="rsl-dash-money-unit">BDT this month</span>
                </div>
            @endif
            <a href="{{ $dueUrl }}" class="rsl-dash-money-tile rsl-dash-money-tile--emerald">
                <span class="rsl-dash-money-label">আজকের আদায়</span>
                <span class="rsl-dash-money-value">{{ number_format($metrics['today_collection'], 0) }}</span>
                <span class="rsl-dash-money-unit">BDT · {{ $metrics['today_collection_count'] }} payments</span>
            </a>
            @if ($hqDueUrl)
                <a href="{{ $hqDueUrl }}" class="rsl-dash-money-tile rsl-dash-money-tile--indigo">
                    <span class="rsl-dash-money-label">HQ due</span>
                    <span class="rsl-dash-money-value">{{ number_format($metrics['admin_receivable_due'] ?? 0, 0) }}</span>
                    <span class="rsl-dash-money-unit">BDT wholesale</span>
                </a>
            @else
                <div class="rsl-dash-money-tile rsl-dash-money-tile--violet">
                    <span class="rsl-dash-money-label">Active</span>
                    <span class="rsl-dash-money-value">{{ $metrics['customers_active'] }}</span>
                    <span class="rsl-dash-money-unit">/ {{ $metrics['customers_total'] }} subs</span>
                </div>
            @endif
        </section>

        <section class="rsl-dash-block" aria-label="Quick actions">
            <p class="rsl-dash-section-title">দ্রুত কাজ</p>
            <div class="rsl-dash-quick-scroll">
                <div class="rsl-dash-quick">
                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_CREATE))
                        <a href="{{ route('reseller.customers.create') }}" class="rsl-dash-quick-link">
                            <span class="rsl-dash-quick-icon"><x-reseller-icon name="plus" /></span>
                            <span class="rsl-dash-quick-label">নতুন</span>
                        </a>
                    @endif
                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                        <a href="{{ $dueUrl }}" class="rsl-dash-quick-link">
                            <span class="rsl-dash-quick-icon"><x-reseller-icon name="collect" /></span>
                            <span class="rsl-dash-quick-label">আদায়</span>
                        </a>
                    @endif
                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE) && \Illuminate\Support\Facades\Route::has('reseller.invoices.index'))
                        <a href="{{ route('reseller.invoices.index') }}" class="rsl-dash-quick-link">
                            <span class="rsl-dash-quick-icon"><x-reseller-icon name="invoice" /></span>
                            <span class="rsl-dash-quick-label">বিল</span>
                        </a>
                    @endif
                    @if ($hqDueUrl && $portal->canPortal(\App\Support\ResellerPortalPermission::WALLET_VIEW))
                        <a href="{{ $hqDueUrl }}" class="rsl-dash-quick-link">
                            <span class="rsl-dash-quick-icon"><x-reseller-icon name="chart" /></span>
                            <span class="rsl-dash-quick-label">HQ due</span>
                        </a>
                    @endif
                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW))
                        <a href="{{ route('reseller.commissions.index') }}" class="rsl-dash-quick-link">
                            <span class="rsl-dash-quick-icon"><x-reseller-icon name="star" /></span>
                            <span class="rsl-dash-quick-label">কমিশন</span>
                        </a>
                    @endif
                    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_VIEW))
                        <a href="{{ route('reseller.customers.index') }}" class="rsl-dash-quick-link">
                            <span class="rsl-dash-quick-icon"><x-reseller-icon name="users" /></span>
                            <span class="rsl-dash-quick-label">তালিকা</span>
                        </a>
                    @endif
                    @if (\Illuminate\Support\Facades\Route::has('reseller.hub'))
                        <a href="{{ route('reseller.hub') }}" class="rsl-dash-quick-link">
                            <span class="rsl-dash-quick-icon"><x-reseller-icon name="home" /></span>
                            <span class="rsl-dash-quick-label">Hub</span>
                        </a>
                    @endif
                </div>
            </div>
        </section>

        @if (!empty($metrics['alerts']))
            <section class="rsl-dash-block" aria-label="Alerts">
                <p class="rsl-dash-section-title">Alerts</p>
                <div class="rsl-dash-alerts" role="list">
                    @foreach ($metrics['alerts'] as $alert)
                        @php
                            $tone = $alert['tone'] ?? 'violet';
                            $icons = ['rose' => '⚠', 'amber' => '📡', 'sky' => '💳', 'violet' => '🎫'];
                        @endphp
                        <article class="rsl-dash-alert rsl-dash-alert--{{ $tone }}" role="listitem">
                            <span class="rsl-dash-alert-icon" aria-hidden="true">{{ $icons[$tone] ?? '●' }}</span>
                            <div class="rsl-dash-alert-body">
                                <p class="rsl-dash-alert-title">{{ $alert['title'] }}</p>
                                <p class="rsl-dash-alert-value">{{ $alert['value'] }}</p>
                                <p class="rsl-dash-alert-hint">{{ $alert['hint'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Desktop featured KPIs --}}
        <section class="rsl-dash-block rsl-only-desktop" aria-label="Key metrics">
            <p class="rsl-dash-section-title">সারসংক্ষেপ</p>
            <div class="rsl-dash-featured">
                <div class="rsl-dash-card rsl-dash-card--emerald">
                    <div class="rsl-dash-card-head">
                        <div>
                            <p class="rsl-dash-card-label">Today collection</p>
                            <p class="rsl-dash-card-value">{{ number_format($metrics['today_collection'], 0) }} <span class="rsl-dash-unit">BDT</span></p>
                        </div>
                        <span class="rsl-dash-card-icon" aria-hidden="true"><x-reseller-icon name="collect" class="rsl-icon-svg rsl-icon-svg--lg" /></span>
                    </div>
                    <p class="rsl-dash-card-sub">{{ $metrics['today_collection_count'] }} payment(s) · Month {{ number_format($metrics['month_collection'], 0) }} BDT</p>
                </div>
                <div class="rsl-dash-card rsl-dash-card--rose">
                    <div class="rsl-dash-card-head">
                        <div>
                            <p class="rsl-dash-card-label">Customer due</p>
                            <p class="rsl-dash-card-value">{{ number_format($metrics['due_amount'], 0) }} <span class="rsl-dash-unit">BDT</span></p>
                        </div>
                        <span class="rsl-dash-card-icon" aria-hidden="true"><x-reseller-icon name="invoice" class="rsl-icon-svg rsl-icon-svg--lg" /></span>
                    </div>
                    <p class="rsl-dash-card-sub">{{ $metrics['due_customers'] }} subscriber(s) · <a href="{{ $dueUrl }}" class="rsl-link">Collect</a></p>
                </div>
                <div class="rsl-dash-card rsl-dash-card--indigo">
                    <div class="rsl-dash-card-head">
                        <div>
                            <p class="rsl-dash-card-label">HQ payable</p>
                            <p class="rsl-dash-card-value">{{ number_format($metrics['admin_receivable_due'] ?? 0, 0) }} <span class="rsl-dash-unit">BDT</span></p>
                        </div>
                        <span class="rsl-dash-card-icon" aria-hidden="true"><x-reseller-icon name="building" class="rsl-icon-svg rsl-icon-svg--lg" /></span>
                    </div>
                    <p class="rsl-dash-card-sub">Wholesale to admin @if ($hqDueUrl)· <a href="{{ $hqDueUrl }}" class="rsl-link">Ledger</a>@endif</p>
                </div>
                <div class="rsl-dash-card rsl-dash-card--sky">
                    <div class="rsl-dash-card-head">
                        <div>
                            <p class="rsl-dash-card-label">Main wallet</p>
                            <p class="rsl-dash-card-value">{{ number_format($metrics['wallet'], 0) }} <span class="rsl-dash-unit">BDT</span></p>
                        </div>
                        <span class="rsl-dash-card-icon" aria-hidden="true"><x-reseller-icon name="wallet" class="rsl-icon-svg rsl-icon-svg--lg" /></span>
                    </div>
                    <p class="rsl-dash-card-sub">Available {{ number_format($available, 0) }} BDT</p>
                    @if ($creditUsedPct !== null)
                        <div class="rsl-dash-progress" title="Credit {{ $creditUsedPct }}%">
                            <div class="rsl-dash-progress-bar" style="width: {{ $creditUsedPct }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="rsl-dash-block" aria-label="Operations">
            <p class="rsl-dash-section-title">Operations</p>
            <div class="rsl-dash-scroll-row">
                <div class="rsl-dash-chip"><span class="rsl-dash-chip-value" style="color:#4f46e5">{{ $metrics['customers_active'] }}</span><span class="rsl-dash-chip-label">Active</span></div>
                <div class="rsl-dash-chip"><span class="rsl-dash-chip-value" style="color:#f43f5e">{{ $metrics['customers_suspended'] ?? 0 }}</span><span class="rsl-dash-chip-label">Suspend</span></div>
                <div class="rsl-dash-chip"><span class="rsl-dash-chip-value" style="color:#0ea5e9">{{ $metrics['onu_online'] }}</span><span class="rsl-dash-chip-label">ONU</span></div>
                <div class="rsl-dash-chip"><span class="rsl-dash-chip-value" style="color:#f59e0b">{{ number_format($metrics['pending_commission'], 0) }}</span><span class="rsl-dash-chip-label">Comm.</span></div>
                <div class="rsl-dash-chip"><span class="rsl-dash-chip-value">{{ number_format($collectionRate, 0) }}%</span><span class="rsl-dash-chip-label">Collection</span></div>
                <div class="rsl-dash-chip"><span class="rsl-dash-chip-value">{{ $metrics['customers_new_month'] ?? 0 }}</span><span class="rsl-dash-chip-label">New</span></div>
            </div>
        </section>

        @if (!empty($chartData))
            <section class="rsl-dash-block" aria-label="Trends">
                <div class="rsl-dash-chart-header">
                    <p class="rsl-dash-section-title rsl-dash-section-title--flush">30-day trends</p>
                    <div class="rsl-dash-chart-tabs rsl-only-mobile" role="tablist" aria-label="Chart type">
                        <button type="button" class="rsl-dash-chart-tab is-active" role="tab" aria-selected="true" data-chart-tab="collection">Collection</button>
                        <button type="button" class="rsl-dash-chart-tab" role="tab" aria-selected="false" data-chart-tab="revenue">Commission</button>
                        <button type="button" class="rsl-dash-chart-tab" role="tab" aria-selected="false" data-chart-tab="growth">Growth</button>
                    </div>
                </div>
                <div class="rsl-dash-charts">
                    @foreach (['collection' => 'Collection', 'revenue' => 'Commission', 'growth' => 'Subscribers'] as $key => $chartTitle)
                        <div class="rsl-dash-chart-card rsl-dash-chart-pane {{ $loop->first ? 'is-active' : '' }}" data-chart-pane="{{ $key }}">
                            <h3 class="rsl-dash-table-title rsl-only-desktop">{{ $chartTitle }}</h3>
                            <div class="rsl-dash-chart-canvas-wrap">
                                <canvas id="chart-{{ $key }}" aria-label="{{ $chartTitle }} chart"></canvas>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    const data = @json($chartData);
                    const styles = {
                        collection: { type: 'bar', color: '#10b981' },
                        revenue: { type: 'bar', color: '#8b5cf6' },
                        growth: { type: 'line', color: '#3b82f6' }
                    };
                    const gridColor = 'rgba(148, 163, 184, 0.2)';
                    const mobileMq = window.matchMedia('(max-width: 767px)');
                    const instances = {};

                    function buildChart(key) {
                        const el = document.getElementById('chart-' + key);
                        if (!el || typeof Chart === 'undefined' || instances[key]) return;
                        const cfg = styles[key] || styles.collection;
                        const c = cfg.color;
                        instances[key] = new Chart(el, {
                            type: cfg.type,
                            data: {
                                labels: data[key].labels,
                                datasets: [{
                                    data: data[key].values,
                                    backgroundColor: cfg.type === 'line' ? c + '22' : c + 'bb',
                                    borderColor: c,
                                    borderWidth: 2,
                                    fill: cfg.type === 'line',
                                    tension: 0.4,
                                    pointRadius: cfg.type === 'line' ? 0 : undefined,
                                    borderRadius: cfg.type === 'bar' ? 6 : undefined
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: {
                                        grid: { display: false },
                                        ticks: { maxTicksLimit: mobileMq.matches ? 5 : 6, color: '#94a3b8', font: { size: 10 } }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        grid: { color: gridColor },
                                        ticks: { color: '#94a3b8', font: { size: 10 } }
                                    }
                                }
                            }
                        });
                    }

                    Object.keys(data).forEach(buildChart);

                    const tabs = document.querySelectorAll('[data-chart-tab]');
                    const panes = document.querySelectorAll('[data-chart-pane]');
                    tabs.forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const id = btn.getAttribute('data-chart-tab');
                            tabs.forEach(function (t) {
                                t.classList.toggle('is-active', t === btn);
                                t.setAttribute('aria-selected', t === btn ? 'true' : 'false');
                            });
                            panes.forEach(function (p) {
                                p.classList.toggle('is-active', p.getAttribute('data-chart-pane') === id);
                            });
                            if (instances[id]) instances[id].resize();
                        });
                    });

                    mobileMq.addEventListener('change', function () {
                        Object.values(instances).forEach(function (ch) { ch.resize(); });
                    });
                })();
            </script>
        @endif

        <div class="rsl-dash-split">
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                <section class="rsl-dash-table-card" aria-label="Recent payments">
                    <div class="rsl-dash-table-head">
                        <h2 class="rsl-dash-table-title">Recent payments</h2>
                        <a href="{{ route('reseller.customers.index') }}" class="rsl-link rsl-dash-table-link">All</a>
                    </div>
                    @if (isset($recentPayments) && $recentPayments->isNotEmpty())
                        @foreach ($recentPayments->take(6) as $pay)
                            @php
                                $name = $pay->customer?->name ?? 'Subscriber';
                                $payInitials = collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                            @endphp
                            <a href="{{ route('reseller.payments.receipt', $pay) }}" class="rsl-dash-row rsl-dash-row--link" target="_blank" rel="noopener">
                                <span class="rsl-dash-avatar" aria-hidden="true">{{ $payInitials ?: '?' }}</span>
                                <div class="rsl-dash-row-main">
                                    <p class="rsl-dash-row-title">{{ $name }}</p>
                                    <p class="rsl-dash-row-sub">{{ $pay->paid_at?->format('d M · H:i') }}</p>
                                </div>
                                <span class="rsl-dash-row-amount">+{{ number_format((float) $pay->amount, 0) }}</span>
                            </a>
                        @endforeach
                    @else
                        <p class="rsl-dash-empty">No payments yet.</p>
                    @endif
                </section>
            @endif

            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW))
                <section class="rsl-dash-table-card" aria-label="Recent commissions">
                    <div class="rsl-dash-table-head">
                        <h2 class="rsl-dash-table-title">Commissions</h2>
                        <a href="{{ route('reseller.commissions.index') }}" class="rsl-link rsl-dash-table-link">All</a>
                    </div>
                    @forelse ($recentCommissions->take(6) as $row)
                        @php
                            $cName = $row->customer?->name ?? '—';
                            $cInitials = collect(explode(' ', $cName))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                        @endphp
                        <div class="rsl-dash-row">
                            <span class="rsl-dash-avatar rsl-dash-avatar--amber" aria-hidden="true">{{ $cInitials ?: '?' }}</span>
                            <div class="rsl-dash-row-main">
                                <p class="rsl-dash-row-title">{{ $cName }}</p>
                                <p class="rsl-dash-row-sub">{{ $row->earned_at?->format('d M') ?? '—' }} · {{ ucfirst($row->status) }}</p>
                            </div>
                            <span class="rsl-dash-row-amount">+{{ number_format((float) $row->commission_amount, 0) }}</span>
                        </div>
                    @empty
                        <p class="rsl-dash-empty">No commissions yet.</p>
                    @endforelse
                </section>
            @endif
        </div>
    </div>
@endsection
