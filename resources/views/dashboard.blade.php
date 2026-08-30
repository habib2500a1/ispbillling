<x-app-layout>
    <x-slot name="header">
        {{ __('Dashboard') }}
    </x-slot>

    <style>
        .dash-shell { --dash-ink:#1e3a5f; --dash-muted:#64748b; --dash-line:#e8eef5; --dash-soft:#f4f7fb; --dash-brand:#06ad73; --dash-brand-dark:#05885b; }
        .dash-shell .sum-head { display:flex; align-items:center; gap:.5rem; margin-bottom:.85rem; }
        .dash-shell .sum-head .dot { width:10px; height:10px; border-radius:50%; background:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.2); }
        .dash-shell .sum-head h6 { margin:0; font-size:.8rem; letter-spacing:.08em; text-transform:uppercase; color:#1e3a5f; font-weight:800; }
        .dash-shell .sum-card { display:flex; align-items:center; gap:.75rem; background:#fff; border-radius:14px; padding:.9rem 1rem; height:100%; text-decoration:none; color:inherit; box-shadow:0 2px 10px rgba(15,23,42,.04); border:1px solid #eef2f7; border-bottom:3px solid var(--accent, #3b82f6); transition:transform .15s, box-shadow .15s; }
        .dash-shell .sum-card:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(15,23,42,.08); color:inherit; }
        .dash-shell .sum-card .ico { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0; background:var(--ico-bg, #eff6ff); color:var(--ico-fg, #2563eb); }
        .dash-shell .sum-card .eyebrow { font-size:.72rem; color:var(--dash-muted); font-weight:600; line-height:1.1; }
        .dash-shell .sum-card .num { font-size:1.35rem; font-weight:800; color:var(--dash-ink); line-height:1.15; }
        .dash-shell .sum-card .sub { font-size:.78rem; color:#475569; font-weight:600; }
        .dash-shell .dash-kpi { background:#fff; border:1px solid var(--dash-line); border-radius:14px; padding:1.1rem 1.15rem; height:100%; transition:border-color .2s, box-shadow .2s; }
        .dash-shell .dash-kpi:hover { border-color:#cbd5e1; box-shadow:0 8px 24px rgba(15,23,42,.06); }
        .dash-shell .dash-kpi .label { font-size:.72rem; letter-spacing:.06em; text-transform:uppercase; color:var(--dash-muted); font-weight:700; margin-bottom:.35rem; }
        .dash-shell .dash-kpi .value { font-size:1.65rem; font-weight:800; color:var(--dash-ink); line-height:1.15; }
        .dash-shell .dash-kpi .meta { font-size:.82rem; color:var(--dash-muted); margin-top:.55rem; }
        .dash-shell .dash-kpi .icon { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; background:rgba(6,173,115,.12); color:var(--dash-brand-dark); font-size:1.15rem; }
        .dash-shell .dash-section-title { font-size:.75rem; letter-spacing:.08em; text-transform:uppercase; color:var(--dash-muted); font-weight:700; }
        .dash-shell .dash-panel { background:#fff; border:1px solid var(--dash-line); border-radius:14px; overflow:hidden; height:100%; }
        .dash-shell .dash-panel .panel-h { padding:.85rem 1.1rem; border-bottom:1px solid var(--dash-line); background:var(--dash-soft); display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; }
        .dash-shell .dash-panel .panel-b { padding:1rem 1.1rem; }
        .dash-shell .dash-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .55rem; border-radius:999px; font-size:.75rem; font-weight:600; background:var(--dash-soft); color:var(--dash-ink); border:1px solid var(--dash-line); }
        .dash-shell .dash-chip.ok { background:rgba(6,173,115,.1); color:var(--dash-brand-dark); border-color:rgba(6,173,115,.25); }
        .dash-shell a.dash-link { color:inherit; text-decoration:none; }
        .dash-shell .quick-grid a { display:flex; align-items:center; gap:.65rem; padding:.7rem .8rem; border:1px solid var(--dash-line); border-radius:12px; background:#fff; color:var(--dash-ink); text-decoration:none; font-weight:600; font-size:.9rem; transition:border-color .15s, background .15s; }
        .dash-shell .quick-grid a:hover { border-color:var(--dash-brand); background:rgba(6,173,115,.04); }
        .dash-shell .quick-grid a i { color:var(--dash-brand-dark); }
        .dash-shell .chart-wrap { position:relative; min-height:220px; }
        .dash-shell .pct-legend { display:flex; flex-wrap:wrap; gap:.75rem; justify-content:center; margin-top:.75rem; }
        .dash-shell .pct-legend span { font-size:.82rem; font-weight:700; color:#334155; }
        .dash-shell .pct-legend i { display:inline-block; width:10px; height:10px; border-radius:3px; margin-right:.35rem; }
        @media (max-width: 575.98px) {
            .dash-shell .sum-card { padding:.7rem .65rem; gap:.5rem; }
            .dash-shell .sum-card .ico { width:38px; height:38px; font-size:1.05rem; }
            .dash-shell .sum-card .num { font-size:1.1rem; }
            .dash-shell .dash-kpi .value { font-size:1.35rem; }
            .dash-shell .quick-grid a { font-size:.85rem; }
        }
    </style>

    @php
        $cs = $clientSummary ?? [];
        $fs = $financialSummary ?? [];
        $lg = $lineGrowth ?? ['labels' => [], 'new' => [], 'monthly' => []];
        $custUrl = fn (string $filter) => route('customers.index', ['filter' => $filter]);
    @endphp

    <div class="dash-shell">
        {{-- CLIENT SUMMARY --}}
        <div class="sum-head">
            <span class="dot"></span>
            <h6>{{ __('Client Summary') }}</h6>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl-2">
                <a class="sum-card" href="{{ $custUrl('all') }}" style="--accent:#3b82f6;--ico-bg:#eff6ff;--ico-fg:#2563eb;">
                    <div class="ico"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Total') }}</div>
                        <div class="num">{{ number_format($cs['total'] ?? 0) }}</div>
                        <div class="sub">{{ __('Total Client') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="sum-card" href="{{ $custUrl('active') }}" style="--accent:#22c55e;--ico-bg:#ecfdf5;--ico-fg:#16a34a;">
                    <div class="ico"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Active') }}</div>
                        <div class="num">{{ number_format($cs['active'] ?? 0) }}</div>
                        <div class="sub">{{ __('Active Client') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="sum-card" href="{{ $custUrl('online') }}" style="--accent:#38bdf8;--ico-bg:#f0f9ff;--ico-fg:#0284c7;">
                    <div class="ico"><i class="bi bi-wifi"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Online') }}</div>
                        <div class="num">{{ number_format($cs['online'] ?? 0) }}</div>
                        <div class="sub">{{ __('Online') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="sum-card" href="{{ $custUrl('offline') }}" style="--accent:#94a3b8;--ico-bg:#f1f5f9;--ico-fg:#64748b;">
                    <div class="ico"><i class="bi bi-wifi-off"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Offline') }}</div>
                        <div class="num">{{ number_format($cs['offline'] ?? 0) }}</div>
                        <div class="sub">{{ __('Offline') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="sum-card" href="{{ $custUrl('inactive') }}" style="--accent:#ec4899;--ico-bg:#fdf2f8;--ico-fg:#db2777;">
                    <div class="ico"><i class="bi bi-x-circle-fill"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Inactive') }}</div>
                        <div class="num">{{ number_format($cs['inactive'] ?? 0) }}</div>
                        <div class="sub">{{ __('Inactive Client') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="sum-card" href="{{ $custUrl('inactive_due') }}" style="--accent:#f43f5e;--ico-bg:#fff1f2;--ico-fg:#e11d48;">
                    <div class="ico"><i class="bi bi-slash-circle"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Due') }}</div>
                        <div class="num">{{ number_format($cs['inactive_due'] ?? 0) }}</div>
                        <div class="sub">{{ __('Inactive Due') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <a class="sum-card" href="{{ $custUrl('expired') }}" style="--accent:#f59e0b;--ico-bg:#fffbeb;--ico-fg:#d97706;">
                    <div class="ico"><i class="bi bi-exclamation-circle-fill"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Expired') }}</div>
                        <div class="num">{{ number_format($cs['expired'] ?? 0) }}</div>
                        <div class="sub">{{ __('Expired Only') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <a class="sum-card" href="{{ $custUrl('joined_month') }}" style="--accent:#3b82f6;--ico-bg:#eff6ff;--ico-fg:#2563eb;">
                    <div class="ico"><i class="bi bi-person-plus-fill"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('This Month') }}</div>
                        <div class="num">{{ number_format($cs['joined_month'] ?? 0) }}</div>
                        <div class="sub">{{ __('Joined') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <a class="sum-card" href="{{ $custUrl('joined_today') }}" style="--accent:#06b6d4;--ico-bg:#ecfeff;--ico-fg:#0891b2;">
                    <div class="ico"><i class="bi bi-person-fill-add"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Today') }}</div>
                        <div class="num">{{ number_format($cs['joined_today'] ?? 0) }}</div>
                        <div class="sub">{{ __('Today New Line') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <a class="sum-card" href="{{ $custUrl('expired_today') }}" style="--accent:#f97316;--ico-bg:#fff7ed;--ico-fg:#ea580c;">
                    <div class="ico"><i class="bi bi-calendar-x"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Today') }}</div>
                        <div class="num">{{ number_format($cs['expired_today'] ?? 0) }}</div>
                        <div class="sub">{{ __('Expired Today') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <a class="sum-card" href="{{ $custUrl('inactive_today') }}" style="--accent:#ec4899;--ico-bg:#fdf2f8;--ico-fg:#db2777;">
                    <div class="ico"><i class="bi bi-slash-circle"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Today') }}</div>
                        <div class="num">{{ number_format($cs['inactive_today'] ?? 0) }}</div>
                        <div class="sub">{{ __('Inactive Today') }}</div>
                    </div>
                </a>
            </div>
        </div>

        {{-- FINANCIAL SUMMARY --}}
        <div class="sum-head">
            <span class="dot" style="background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.2);"></span>
            <h6>{{ __('Financial Summary') }}</h6>
            @if(($fs['billable_customers'] ?? 0) > 0 && ($fs['bills_generated'] ?? 0) === 0 && ($fs['bill'] ?? 0) > 0)
                <span class="dash-chip">{{ __('Live rent total') }}</span>
            @elseif(($fs['bills_generated'] ?? 0) > 0)
                <span class="dash-chip ok">{{ __('Bills generated') }}: {{ $fs['bills_generated'] }}</span>
            @endif
            @if(canSellSaas() || hasAccess(['Super Admin'], ['automatic-processes']))
                <a href="{{ route('automatic-processes') }}" class="btn btn-sm btn-outline-secondary">{{ __('Generate bills') }}</a>
            @endif
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="sum-card" style="--accent:#3b82f6;--ico-bg:#eff6ff;--ico-fg:#2563eb;cursor:default;">
                    <div class="ico"><i class="bi bi-receipt"></i></div>
                    <div>
                        <div class="eyebrow">{{ $fs['month_label'] ?? now()->format('F Y') }}</div>
                        <div class="num">{{ number_format($fs['bill'] ?? 0) }}</div>
                        <div class="sub">{{ __('Total Bill') }}@if(($fs['billable_customers'] ?? 0) > 0) · {{ $fs['billable_customers'] }}@endif</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="sum-card" href="{{ route('payment-collection') }}" style="--accent:#38bdf8;--ico-bg:#f0f9ff;--ico-fg:#0284c7;">
                    <div class="ico"><i class="bi bi-cash"></i></div>
                    <div>
                        <div class="eyebrow">{{ __('Today') }}</div>
                        <div class="num">{{ number_format($fs['today_collection'] ?? 0) }}</div>
                        <div class="sub">{{ __('Collection') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="sum-card" href="{{ route('accounts-hub') }}" style="--accent:#22c55e;--ico-bg:#ecfdf5;--ico-fg:#16a34a;">
                    <div class="ico"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="eyebrow">{{ $fs['month_label'] ?? '' }}</div>
                        <div class="num">{{ number_format($fs['collection'] ?? 0) }}</div>
                        <div class="sub">{{ __('Collection') }} · {{ $fs['collection_pct'] ?? 0 }}%</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="sum-card" style="--accent:#f43f5e;--ico-bg:#fff1f2;--ico-fg:#e11d48;cursor:default;">
                    <div class="ico"><i class="bi bi-arrow-repeat"></i></div>
                    <div>
                        <div class="eyebrow">{{ $fs['month_label'] ?? '' }}</div>
                        <div class="num">{{ number_format($fs['due'] ?? 0) }}</div>
                        <div class="sub">{{ __('Due') }} · {{ $fs['due_pct'] ?? 0 }}%</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="sum-card" style="--accent:#f59e0b;--ico-bg:#fffbeb;--ico-fg:#d97706;cursor:default;">
                    <div class="ico"><i class="bi bi-percent"></i></div>
                    <div>
                        <div class="eyebrow">{{ $fs['month_label'] ?? '' }}</div>
                        <div class="num">{{ number_format($fs['discount'] ?? 0) }}</div>
                        <div class="sub">{{ __('Discount') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="sum-card" style="--accent:#fb923c;--ico-bg:#fff7ed;--ico-fg:#ea580c;cursor:default;">
                    <div class="ico"><i class="bi bi-wallet-fill"></i></div>
                    <div>
                        <div class="eyebrow">{{ now()->format('M Y') }}</div>
                        <div class="num">{{ number_format($fs['expense'] ?? 0) }}</div>
                        <div class="sub">{{ __('Expense') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Graphs: collection vs due % + last 6 month line growth --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-4">
                <div class="dash-panel">
                    <div class="panel-h">
                        <span class="dash-section-title mb-0">{{ __('Collection vs Due') }}</span>
                        <span class="dash-chip">{{ $fs['month_label'] ?? '' }}</span>
                    </div>
                    <div class="panel-b">
                        <div class="chart-wrap" style="max-width:260px;margin:0 auto;">
                            <canvas id="dashCollectDueChart" height="220"></canvas>
                        </div>
                        <div class="pct-legend">
                            <span><i style="background:#22c55e;"></i>{{ __('Collection') }} {{ $fs['collection_pct'] ?? 0 }}%</span>
                            <span><i style="background:#f43f5e;"></i>{{ __('Due') }} {{ $fs['due_pct'] ?? 0 }}%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="dash-panel">
                    <div class="panel-h">
                        <span class="dash-section-title mb-0">{{ __('Line Growth') }}</span>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ $custUrl('joined_today') }}" class="btn btn-sm btn-outline-info">{{ __('Today New') }} ({{ $cs['joined_today'] ?? 0 }})</a>
                            <a href="{{ $custUrl('joined_month') }}" class="btn btn-sm btn-outline-primary">{{ __('This Month') }} ({{ $cs['joined_month'] ?? 0 }})</a>
                            <span class="dash-chip">{{ __('Last 6 months') }}</span>
                        </div>
                    </div>
                    <div class="panel-b">
                        <div class="chart-wrap">
                            <canvas id="dashLineGrowthChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Network + Ops panels --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-7">
                <div class="dash-panel">
                    <div class="panel-h">
                        <span class="dash-section-title mb-0">{{ __('Network & Optical') }}</span>
                        <div class="d-flex flex-wrap gap-2">
                            @if($opticalData['bridge'] ?? false)
                                <span class="dash-chip ok">{{ __('Live bridge') }}</span>
                            @else
                                <span class="dash-chip">{{ __('Local inventory') }}</span>
                            @endif
                            <a href="{{ route('mikrotik-sync') }}" class="btn btn-sm btn-outline-secondary">{{ __('MikroTik') }}</a>
                            <a href="{{ route('olt-management') }}" class="btn btn-sm btn-outline-secondary">{{ __('OLT') }}</a>
                            <a href="{{ route('onu-management') }}" class="btn btn-sm btn-success">{{ __('ONU') }}</a>
                        </div>
                    </div>
                    <div class="panel-b">
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="label text-muted small text-uppercase fw-bold">{{ __('Routers') }}</div>
                                <div class="fs-4 fw-bold">{{ $networkQuick['routers'] ?? 0 }}</div>
                                <div class="small text-muted">{{ __('online') }} {{ $networkQuick['routers_online'] ?? ($networkQuick['online_routers'] ?? '—') }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="label text-muted small text-uppercase fw-bold">{{ __('OLT') }}</div>
                                <div class="fs-4 fw-bold">{{ $opticalData['olts'] ?? ($networkQuick['olts'] ?? 0) }}</div>
                                <div class="small text-muted">{{ __('ports / PON tracked') }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="label text-muted small text-uppercase fw-bold">{{ __('ONU') }}</div>
                                <div class="fs-4 fw-bold">{{ $opticalData['onus'] ?? ($networkQuick['onus_local'] ?? 0) }}</div>
                                <div class="small text-muted">{{ __('local') }} {{ $networkQuick['onus_local'] ?? 0 }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="label text-muted small text-uppercase fw-bold">{{ __('PPP online') }}</div>
                                <div class="fs-4 fw-bold">{{ $networkQuick['ppp_online'] ?? ($customersData['active'] ?? 0) }}</div>
                                <div class="small text-muted"><a href="{{ route('online-clients') }}">{{ __('View live') }}</a></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="dash-panel">
                    <div class="panel-h">
                        <span class="dash-section-title mb-0">{{ __('Quick actions') }}</span>
                        <a href="{{ route('ops-insights') }}" class="btn btn-sm btn-outline-secondary">{{ __('Insights') }}</a>
                    </div>
                    <div class="panel-b quick-grid">
                        <div class="row g-2">
                            <div class="col-6"><a href="{{ route('payment-collection') }}"><i class="bi bi-cash-coin"></i> {{ __('Collect bill') }}</a></div>
                            <div class="col-6"><a href="{{ route('customers.index') }}"><i class="bi bi-person-lines-fill"></i> {{ __('Customers') }}</a></div>
                            <div class="col-6"><a href="{{ route('billing-notices') }}"><i class="bi bi-bell"></i> {{ __('Notices') }}</a></div>
                            <div class="col-6"><a href="{{ route('noc-overview') }}"><i class="bi bi-radar"></i> {{ __('NOC') }}</a></div>
                            <div class="col-6"><a href="{{ route('call-desk') }}"><i class="bi bi-telephone"></i> {{ __('Call desk') }}</a></div>
                            <div class="col-6"><a href="{{ route('accounts-hub') }}"><i class="bi bi-journal-richtext"></i> {{ __('Accounts') }}</a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ops summary chips --}}
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <span class="dash-section-title">{{ __('Ops Command Center') }}</span>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('accounts-hub') }}" class="btn btn-sm btn-outline-secondary">{{ __('Accounts') }}</a>
                        <a href="{{ route('sms-notices') }}" class="btn btn-sm btn-outline-secondary">{{ __('SMS') }}</a>
                        <a href="{{ route('noc-overview') }}" class="btn btn-sm btn-success">{{ __('NOC') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <a class="dash-link" href="{{ route('accounts-hub') }}">
                    <div class="dash-kpi">
                        <div class="label">{{ __('Today collection') }}</div>
                        <div class="value fs-3">৳{{ number_format($opsData['today_collection'] ?? 0, 0) }}</div>
                        <div class="meta">{{ __('Accounts Hub') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a class="dash-link" href="{{ route('billing-notices') }}">
                    <div class="dash-kpi">
                        <div class="label">{{ __('Billing notices') }}</div>
                        <div class="value fs-3">{{ $opsData['overdue_notices'] ?? 0 }}</div>
                        <div class="meta">{{ __('Due soon') }}: {{ $opsData['due_soon'] ?? 0 }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a class="dash-link" href="{{ route('noc-overview') }}">
                    <div class="dash-kpi">
                        <div class="label">{{ __('Tickets / SLA') }}</div>
                        <div class="value fs-3">{{ $opsData['open_tickets'] ?? 0 }}</div>
                        <div class="meta {{ ($opsData['sla_breached'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ __('SLA breached') }}: {{ $opsData['sla_breached'] ?? 0 }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a class="dash-link" href="{{ route('call-desk') }}">
                    <div class="dash-kpi">
                        <div class="label">{{ __('Call Desk') }}</div>
                        <div class="value fs-3">{{ $opsData['calls_today'] ?? 0 }}</div>
                        <div class="meta">{{ __('Callbacks') }}: {{ $opsData['callbacks'] ?? 0 }}</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const collect = {{ (float) ($fs['collection'] ?? 0) }};
            const due = {{ (float) ($fs['due'] ?? 0) }};
            const collectPct = {{ (float) ($fs['collection_pct'] ?? 0) }};
            const duePct = {{ (float) ($fs['due_pct'] ?? 0) }};
            const labels = @json($lg['labels'] ?? []);
            const newLines = @json($lg['new'] ?? []);
            const monthlyLines = @json($lg['monthly'] ?? []);

            const donutEl = document.getElementById('dashCollectDueChart');
            if (donutEl && window.Chart) {
                new Chart(donutEl, {
                    type: 'doughnut',
                    data: {
                        labels: ['{{ __('Collection') }} ' + collectPct + '%', '{{ __('Due') }} ' + duePct + '%'],
                        datasets: [{
                            data: [collect || 0.01, due || 0.01],
                            backgroundColor: ['#22c55e', '#f43f5e'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        cutout: '62%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const total = (collect + due) || 1;
                                        const val = ctx.raw || 0;
                                        const pct = ((val / total) * 100).toFixed(1);
                                        return ctx.label.split(' ')[0] + ': ৳' + Number(val).toLocaleString() + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            const lineEl = document.getElementById('dashLineGrowthChart');
            if (lineEl && window.Chart) {
                new Chart(lineEl, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: '{{ __('New lines / month') }}',
                                data: newLines,
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59,130,246,.15)',
                                fill: true,
                                tension: 0.35,
                                pointRadius: 4,
                                pointBackgroundColor: '#3b82f6'
                            },
                            {
                                label: '{{ __('Total lines (cumulative)') }}',
                                data: monthlyLines,
                                borderColor: '#06ad73',
                                backgroundColor: 'rgba(6,173,115,.08)',
                                fill: false,
                                tension: 0.35,
                                pointRadius: 4,
                                pointBackgroundColor: '#06ad73'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { position: 'bottom' } },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        })();
    </script>
    @endpush

    {{-- Legacy gradient Ops Command Center removed — cleaned above --}}
    <div class="d-none">
        <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                <h6 class="text-uppercase text-muted fw-bold mb-0" style="font-size: 0.75rem; letter-spacing: 0.08em;">
                    {{ __('Ops Command Center') }}
                </h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('accounts-hub') }}" class="btn btn-sm btn-outline-secondary">{{ __('Accounts') }}</a>
                    <a href="{{ route('call-desk') }}" class="btn btn-sm btn-outline-secondary">{{ __('Call Desk') }}</a>
                    <a href="{{ route('sms-notices') }}" class="btn btn-sm btn-outline-secondary">{{ __('SMS') }}</a>
                    <a href="{{ route('ops-insights') }}" class="btn btn-sm btn-outline-secondary">{{ __('Insights') }}</a>
                    <a href="{{ route('noc-overview') }}" class="btn btn-sm btn-primary">{{ __('NOC') }}</a>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #0f172a, #1e3a5f); color: #fff; border-radius: 12px;">
                <div class="card-body position-relative z-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('OLT / ONU') }}</h6>
                            <h3 class="fw-bold mb-0">{{ $opticalData['olts'] ?? 0 }} <span style="font-size: 0.85rem; font-weight: normal; opacity: 0.75;">OLT</span></h3>
                            <div class="mt-1" style="font-size: 1.1rem; font-weight: 700;">{{ number_format($opticalData['onus'] ?? 0) }} <span style="font-size: 0.8rem; font-weight: normal; opacity: 0.75;">ONU</span></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.15);">
                            <i class="bi bi-hdd-network fs-4 text-info"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top border-white border-opacity-10 small" style="opacity: 0.9;">
                        {{ __('Linked:') }} <strong>{{ $opticalData['linked'] ?? 0 }}</strong>
                        <span class="float-end">{{ __('Local OLT:') }} {{ $networkQuick['olts_local'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #059669, #047857); color: #fff; border-radius: 12px;">
                <div class="card-body position-relative z-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('RX Signal OK') }}</h6>
                            <h3 class="fw-bold mb-0">{{ $opticalData['rx_ok'] ?? 0 }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                            <i class="bi bi-reception-4 fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                        {{ __('Avg RX:') }}
                        <strong>{{ isset($opticalData['avg_rx']) && $opticalData['avg_rx'] !== null ? number_format($opticalData['avg_rx'], 2).' dBm' : '—' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #d97706, #b45309); color: #fff; border-radius: 12px;">
                <div class="card-body position-relative z-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Weak / Critical RX') }}</h6>
                            <h3 class="fw-bold mb-0">{{ ($opticalData['rx_weak'] ?? 0) + ($opticalData['rx_critical'] ?? 0) }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                            <i class="bi bi-exclamation-triangle fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                        {{ __('Weak:') }} <strong>{{ $opticalData['rx_weak'] ?? 0 }}</strong>
                        <span class="float-end">{{ __('Critical:') }} <strong>{{ $opticalData['rx_critical'] ?? 0 }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #4f46e5, #4338ca); color: #fff; border-radius: 12px;">
                <div class="card-body position-relative z-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Mikrotik Routers') }}</h6>
                            <h3 class="fw-bold mb-0">{{ $networkQuick['routers_connected'] ?? 0 }} <span style="font-size: 0.85rem; font-weight: normal; opacity: 0.75;">/ {{ $networkQuick['routers'] ?? 0 }}</span></h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                            <i class="bi bi-router fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                        {{ __('Connected routers') }}
                        <span class="float-end">{{ __('Local ONU:') }} {{ $networkQuick['onus_local'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Ops Command Center — hubs from billing / support / stock / HR / bandwidth --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                <h6 class="text-uppercase text-muted fw-bold mb-0" style="font-size: 0.75rem; letter-spacing: 0.08em;">
                    {{ __('Ops Command Center') }}
                </h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('accounts-hub') }}" class="btn btn-sm btn-outline-secondary">{{ __('Accounts') }}</a>
                    <a href="{{ route('call-desk') }}" class="btn btn-sm btn-outline-secondary">{{ __('Call Desk') }}</a>
                    <a href="{{ route('sms-notices') }}" class="btn btn-sm btn-outline-secondary">{{ __('SMS') }}</a>
                    <a href="{{ route('ops-insights') }}" class="btn btn-sm btn-outline-secondary">{{ __('Insights') }}</a>
                    <a href="{{ route('noc-overview') }}" class="btn btn-sm btn-primary">{{ __('NOC') }}</a>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('accounts-hub') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #059669, #047857); color: #fff; border-radius: 12px;">
                    <div class="card-body position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Today collection') }}</h6>
                                <h3 class="fw-bold mb-0">{{ number_format($opsData['today_collection'] ?? 0, 2) }}</h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                                <i class="bi bi-cash-stack fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                            {{ __('Accounts Hub') }}
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('billing-notices') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #b45309, #92400e); color: #fff; border-radius: 12px;">
                    <div class="card-body position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Billing notices') }}</h6>
                                <h3 class="fw-bold mb-0">{{ $opsData['overdue_notices'] ?? 0 }}</h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                                <i class="bi bi-bell fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                            {{ __('Overdue') }}
                            <span class="float-end">{{ __('Due soon:') }} {{ $opsData['due_soon'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('noc-overview') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #991b1b, #7f1d1d); color: #fff; border-radius: 12px;">
                    <div class="card-body position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Tickets / SLA') }}</h6>
                                <h3 class="fw-bold mb-0">{{ $opsData['open_tickets'] ?? 0 }}</h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                                <i class="bi bi-headset fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                            {{ __('Open') }}
                            <span class="float-end">{{ __('SLA breached:') }} {{ $opsData['sla_breached'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('call-desk') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #0f172a, #1e3a5f); color: #fff; border-radius: 12px;">
                    <div class="card-body position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Call Desk') }}</h6>
                                <h3 class="fw-bold mb-0">{{ $opsData['calls_today'] ?? 0 }}</h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                                <i class="bi bi-telephone-forward fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                            {{ __('Calls today') }}
                            <span class="float-end">{{ __('Callbacks:') }} {{ $opsData['callbacks'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('inventory-hub') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #4f46e5, #4338ca); color: #fff; border-radius: 12px;">
                    <div class="card-body position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Inventory') }}</h6>
                                <h3 class="fw-bold mb-0">{{ $opsData['low_stock'] ?? 0 }}</h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                                <i class="bi bi-box-seam fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                            {{ __('Low stock') }}
                            <span class="float-end">{{ __('Products:') }} {{ $opsData['products'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('hr-hub') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #0d9488, #14b8a6); color: #fff; border-radius: 12px;">
                    <div class="card-body position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('HR today') }}</h6>
                                <h3 class="fw-bold mb-0">{{ $opsData['present_today'] ?? 0 }}</h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                                <i class="bi bi-person-badge fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                            {{ __('Present') }}
                            <span class="float-end">{{ __('Pending leave:') }} {{ $opsData['pending_leaves'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('bandwidth-hub') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #1e293b, #334155); color: #fff; border-radius: 12px;">
                    <div class="card-body position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Bandwidth') }}</h6>
                                <h3 class="fw-bold mb-0">{{ $opsData['routers_connected'] ?? 0 }} <span style="font-size: 0.85rem; font-weight: normal; opacity: 0.75;">/ {{ $opsData['routers'] ?? 0 }}</span></h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                                <i class="bi bi-speedometer2 fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                            {{ __('Connected routers') }}
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('ops-insights') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #7c2d12, #9a3412); color: #fff; border-radius: 12px;">
                    <div class="card-body position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.85;">{{ __('Ops Insights') }}</h6>
                                <h3 class="fw-bold mb-0">{{ $opsData['insights_critical'] ?? 0 }}</h3>
                            </div>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.2);">
                                <i class="bi bi-lightning-charge fs-4"></i>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-white border-opacity-25 small" style="opacity: 0.9;">
                            {{ __('Critical') }}
                            <span class="float-end">{{ __('High:') }} {{ $opsData['insights_high'] ?? 0 }} · {{ __('All:') }} {{ $opsData['insights_total'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- Reseller Overview Stat Cards Row --}}
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Resellers -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #1e293b, #334155); color: #fff; border-radius: 12px;">
                <div class="card-body position-relative z-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-bold mb-1 text-white-50" style="font-size: 0.72rem; letter-spacing: 1px;">{{ __('Total Resellers') }}</h6>
                            <h3 class="fw-bold mb-0">{{ $resellerData['total_resellers'] ?? 0 }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.15);">
                            <i class="bi bi-people fs-4 text-info"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top border-white border-opacity-10 small text-white-50">
                        <span>{{ __('Active:') }} <strong>{{ $resellerData['active_resellers'] ?? 0 }}</strong></span>
                        <span class="float-end">{{ __('Suspended:') }} <strong>{{ $resellerData['suspended_resellers'] ?? 0 }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Reseller Customers -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; border-radius: 12px;">
                <div class="card-body position-relative z-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-bold mb-1 text-white-50" style="font-size: 0.72rem; letter-spacing: 1px;">{{ __('Reseller Customers') }}</h6>
                            <h3 class="fw-bold mb-0">{{ $resellerData['total_customers'] ?? 0 }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.15);">
                            <i class="bi bi-person-check fs-4 text-warning"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top border-white border-opacity-10 small text-white-50">
                        <span>{{ __('Active:') }} <strong>{{ $resellerData['active_customers'] ?? 0 }}</strong></span>
                        <span class="float-end">{{ __('Pending:') }} <strong>{{ $resellerData['pending_customers'] ?? 0 }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Total Reseller Balance -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #0d9488, #14b8a6); color: #fff; border-radius: 12px;">
                <div class="card-body position-relative z-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-bold mb-1 text-white-50" style="font-size: 0.72rem; letter-spacing: 1px;">{{ __('Wallet Balances') }}</h6>
                            <h3 class="fw-bold mb-0">৳{{ number_format($resellerData['total_balance'] ?? 0, 2) }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.15);">
                            <i class="bi bi-cash-stack fs-4 text-success"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top border-white border-opacity-10 small text-white-50">
                        {{ __('Cumulative reseller wallet balance.') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: Total Reseller Commission -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="background: linear-gradient(135deg, #db2777, #ec4899); color: #fff; border-radius: 12px;">
                <div class="card-body position-relative z-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-bold mb-1 text-white-50" style="font-size: 0.72rem; letter-spacing: 1px;">{{ __('Total Commissions') }}</h6>
                            <h3 class="fw-bold mb-0">৳{{ number_format($resellerData['total_commission'] ?? 0, 2) }}</h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.15);">
                            <i class="bi bi-percent fs-4 text-light"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top border-white border-opacity-10 small text-white-50">
                        {{ __('Total reseller commission awarded.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>{{-- /legacy gradient blocks --}}

    <div class="row g-4 mb-4">
        {{-- Unified Row for Routers and Graphs to allow dynamic "weight" adjustment --}}
        @foreach (($systemOverview ?? []) as $routerName => $routerData)
            @php
                if (! is_array($routerData) || ! ($routerData['status'] ?? false)) {
                    continue;
                }

                $rawInfo = $routerData['data'][0] ?? $routerData['data'] ?? [];
                $info = is_array($rawInfo) ? $rawInfo : [];

                $cpuLoad = (int) filter_var($info['cpu-load'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
                $cpuColor = $cpuLoad > 80 ? 'bg-danger' : ($cpuLoad > 50 ? 'bg-warning' : 'bg-success');

                // Memory Math
                $memTotal = (int)($info['total-memory'] ?? 1);
                $memFree = (int)($info['free-memory'] ?? 0);
                $memUsed = $memTotal - $memFree;
                $memPct = $memTotal > 0 ? round(($memUsed / $memTotal) * 100) : 0;

                // HDD Math
                $hddTotal = (int)($info['total-hdd-space'] ?? 1);
                $hddFree = (int)($info['free-hdd-space'] ?? 0);
                $hddUsed = $hddTotal - $hddFree;
                $hddPct = $hddTotal > 0 ? round(($hddUsed / $hddTotal) * 100) : 0;

                $cardId = 'router_' . \Illuminate\Support\Str::slug($routerName);

                if (!function_exists('formatRouterBytesLg')) {
                    function formatRouterBytesLg($bytes) {
                        if ($bytes == 0) return '0 B';
                        $k = 1024;
                        $sizes = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
                        $i = floor(log($bytes) / log($k));
                        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
                    }
                }
            @endphp

            <div class="col-12 col-md-6 col-lg-4 col-xxl-4 d-flex flex-fill">
                {{-- Refined Router Card with Full Details --}}
                <div class="card border-0 shadow-sm rounded-4 w-100 overflow-hidden d-flex flex-column router-overview-card" style="min-height: 460px;">
                    <div class="px-3 py-2" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: white;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-hdd-network fs-2 text-info me-3" style="font-size: 1.5rem;"></i>
                            <div class="flex-grow-1 overflow-hidden">
                                <h5 class="fw-bold mb-0 text-white text-truncate">{{ $info['board-name'] ?? $routerName }}</h5>
                                <div class="text-white-50 mt-1 d-flex gap-2 flex-wrap align-items-center" style="font-size: 0.7rem;">
                                    <span class="badge bg-info bg-opacity-25 text-dark border border-info border-opacity-25 p-1">{{ strtoupper($info['platform'] ?? 'N/A') }}</span>
                                    <span>•</span>
                                    <span class="text-white text-opacity-75">{{ $info['architecture-name'] ?? 'N/A' }}</span>
                                    <span>•</span>
                                    <span class="badge {{
                                        ($routerData['type'] ?? '') === 'API' ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                                    }}">
                                        {{ __(':type Connection', ['type' => $routerData['type'] ?? 'N/A']) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body bg-light router-card-body p-0 d-flex flex-column h-100">
                        <div class="px-4 py-2 border-bottom router-card-subheader">
                            <div class="row text-center mb-4">
                                <div class="col">
                                    <small class="text-muted d-block mb-1 fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">{{ __('Uptime') }}</small>
                                    <span class="fw-bold text-dark uptime-clock"
                                          style="font-size: 0.85rem;"
                                          data-uptime="{{ $info['uptime'] ?? '0s' }}">
                                        {{ str_replace(['w', 'd', 'h', 'm', 's'], ['w ', 'd ', 'h ', 'm ', 's '], $info['uptime'] ?? 'N/A') }}
                                    </span>
                                </div>
                                <div class="col border-start">
                                    <small class="text-muted d-block mb-1 fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">{{ __('Version') }}</small>
                                    <span class="fw-bold text-dark" style="font-size: 0.85rem;">{{ $info['version'] ?? 'N/A' }}</span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between text-dark mb-1" style="font-size:0.75rem; font-weight:700;">
                                        <span>{{ __('CPU Usage') }}</span>
                                        <span class="text-info fw-bold">{{ ($info['cpu-count'] ?? '?') . ' × ' . ($info['cpu-frequency'] ?? '?') }} MHz<span class="text-muted fw-normal">({{ $cpuLoad }}%)</span></span>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius:10px; background: rgba(0,0,0,0.05);">
                                        <div class="progress-bar {{ $cpuColor }}" role="progressbar" style="width: {{ $cpuLoad }}%;"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between text-dark mb-1" style="font-size:0.75rem; font-weight:700;">
                                        <span>{{ __('Memory Usage') }}</span>
                                        <span>{{ formatRouterBytesLg($memUsed) }} / {{ formatRouterBytesLg($memTotal) }} <span class="text-muted fw-normal ms-1">({{ $memPct }}%)</span></span>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius:10px; background: rgba(0,0,0,0.05);">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $memPct }}%;"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between text-dark mb-1" style="font-size:0.75rem; font-weight:700;">
                                        <span>{{ __('Storage Usage') }}</span>
                                        <span>{{ formatRouterBytesLg($hddUsed) }} / {{ formatRouterBytesLg($hddTotal) }} <span class="text-muted fw-normal ms-1">({{ $hddPct }}%)</span></span>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius:10px; background: rgba(0,0,0,0.05);">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $hddPct }}%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex-grow-1 overflow-auto">
                            <div class="accordion accordion-flush" id="acc_{{ $cardId }}">

                                {{-- Part 1: Platform & Architecture --}}
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-3 px-4 fw-bold router-accordion-button text-info" type="button" data-bs-toggle="collapse" data-bs-target="#plat_{{ $cardId }}" style="font-size: 0.72rem;">
                                            <i class="bi bi-info-square me-2"></i> {{ __('Platform & Architecture') }}
                                        </button>
                                    </h2>
                                    <div id="plat_{{ $cardId }}" class="accordion-collapse collapse" data-bs-parent="#acc_{{ $cardId }}">
                                        <div class="accordion-body p-3 router-accordion-body border-top">
                                            <div class="d-flex flex-column gap-2" style="font-size: 0.72rem;">
                                                <div class="d-flex justify-content-between"><span>{{ __('Board Name') }}</span><span class="fw-bold">{{ $info['board-name'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Platform') }}</span><span class="fw-bold">{{ $info['platform'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Architecture') }}</span><span class="fw-bold text-info">{{ $info['architecture-name'] ?? 'N/A' }}</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Part 2: System & Software --}}
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-3 px-4 fw-bold router-accordion-button text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sys_{{ $cardId }}" style="font-size: 0.72rem;">
                                            <i class="bi bi-gear-wide-connected me-2"></i> {{ __('System Diagnostics & Build') }}
                                        </button>
                                    </h2>
                                    <div id="sys_{{ $cardId }}" class="accordion-collapse collapse" data-bs-parent="#acc_{{ $cardId }}">
                                        <div class="accordion-body p-3 router-accordion-body border-top">
                                            <div class="d-flex flex-column gap-2" style="font-size: 0.72rem;">
                                                <div class="d-flex justify-content-between"><span>{{ __('OS Version') }}</span><span class="fw-bold text-primary">{{ $info['version'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Factory OS') }}</span><span class="fw-bold">{{ $info['factory-software'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Build Timestamp') }}</span><span class="fw-bold">{{ $info['build-time'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Uptime') }}</span><span class="fw-bold text-dark">{{ $info['uptime'] ?? 'N/A' }}</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Part 3: Hardware Information --}}
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-3 px-4 fw-bold router-accordion-button text-warning" type="button" data-bs-toggle="collapse" data-bs-target="#hw_{{ $cardId }}" style="font-size: 0.72rem;">
                                            <i class="bi bi-gpu-card me-2"></i> {{ __('Hardware Information') }}
                                        </button>
                                    </h2>
                                    <div id="hw_{{ $cardId }}" class="accordion-collapse collapse" data-bs-parent="#acc_{{ $cardId }}">
                                        <div class="accordion-body p-3 router-accordion-body border-top">
                                            <div class="d-flex flex-column gap-2" style="font-size: 0.72rem;">
                                                <div class="d-flex justify-content-between"><span>{{ __('CPU') }}</span><span class="fw-bold">{{ $info['cpu'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('CPU count/freq/load') }}</span><span class="fw-bold text-info">{{ ($info['cpu-count'] ?? '?') }} / {{ ($info['cpu-frequency'] ?? '?') }} / {{ $info['cpu-load'] ?? '0' }}%</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Hdd') }}</span><span class="fw-bold text-dark">{{ formatRouterBytesLg($hddUsed) }} / {{ formatRouterBytesLg($hddTotal) }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Write Total') }}</span><span class="fw-bold text-warning">{{ $info['write-sect-total'] ?? '0' }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Write Since Reboot') }}</span><span class="fw-bold text-warning">{{ $info['write-sect-since-reboot'] ?? '0' }}</span></div>
                                                <div class="d-flex justify-content-between"><span>{{ __('Temp / Voltage') }}</span><span class="fw-bold text-dark"><span class="text-danger">{{ isset($info['temperature']) ? $info['temperature'].'°C' : 'N/A' }}</span> | <span class="text-primary">{{ isset($info['voltage']) ? $info['voltage'].'V' : 'N/A' }}</span></span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Analytical Graphs Section --}}
        <div class="col-12 col-md-6 col-lg-4 col-xxl-4 d-flex flex-fill">
            <div class="card border-0 shadow-sm rounded-4 w-100 overflow-hidden d-flex flex-column" style="min-height: 460px; border: 1px solid rgba(0,0,0,0.08); background: var(--bs-card-bg);">
                <div class="card-header py-3 border-0">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i>{{ __('Customer Segmentation') }}</h6>
                </div>
                <div class="card-body p-0 d-flex align-items-center justify-content-center" id="customers"></div>
            </div>
        </div>

        {{-- Billing Overview: full-width, two-panel --}}
        <div class="col-12 d-flex flex-fill">
            <div class="card border-0 shadow-sm rounded-4 w-100 overflow-hidden" style="border: 1px solid rgba(0,0,0,0.08); background: var(--bs-card-bg);">
                <div class="card-header py-3 border-0 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cash-stack text-success me-2"></i>{{ __('Billing Overview') }}</h6>
                    <div class="d-flex gap-1 flex-wrap">
                        <span class="badge rounded-pill px-2 py-1" style="font-size:10px;background:rgba(34,197,94,0.12);color:#16a34a;"><i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>{{ __('Active') }}</span>
                        <span class="badge rounded-pill px-2 py-1" style="font-size:10px;background:rgba(99,102,241,0.12);color:#4f46e5;"><i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>{{ __('Free') }}</span>
                        <span class="badge rounded-pill px-2 py-1" style="font-size:10px;background:rgba(239,68,68,0.12);color:#dc2626;"><i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>{{ __('Inactive') }}</span>
                        <span class="badge rounded-pill px-2 py-1" style="font-size:10px;background:rgba(251,146,60,0.12);color:#ea580c;"><i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>{{ __('Pending') }}</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="row g-0">
                        {{-- LEFT: Global Summary Chart (all 8 metrics) --}}
                        <div class="col-12 col-lg-7 border-end" style="border-color:rgba(0,0,0,0.06)!important;">
                            <div class="px-2 pt-2 pb-0">
                                <p class="mb-0 text-muted fw-semibold" style="font-size:11px;letter-spacing:.5px;text-transform:uppercase;">{{ __('Global Summary') }}</p>
                            </div>
                            <div id="billInformation"></div>
                        </div>
                        {{-- RIGHT: Per-Status Grouped Chart + Mini-tiles --}}
                        <div class="col-12 col-lg-5 d-flex flex-column">
                            <div class="px-2 pt-2 pb-0">
                                <p class="mb-0 text-muted fw-semibold" style="font-size:11px;letter-spacing:.5px;text-transform:uppercase;">{{ __('By Status') }}</p>
                            </div>
                            <div id="billByStatus" style="flex:1;"></div>
                            {{-- Summary mini-tiles --}}
                            <div class="row g-0 border-top" style="border-color:rgba(0,0,0,0.06)!important;">
                                @php
                                    $tileConfig = [
                                        'active'   => ['color'=>'#16a34a','bg'=>'rgba(34,197,94,0.08)','icon'=>'bi-check-circle-fill'],
                                        'free'     => ['color'=>'#4f46e5','bg'=>'rgba(99,102,241,0.08)','icon'=>'bi-gift-fill'],
                                        'inactive' => ['color'=>'#dc2626','bg'=>'rgba(239,68,68,0.08)','icon'=>'bi-x-circle-fill'],
                                        'pending'  => ['color'=>'#ea580c','bg'=>'rgba(251,146,60,0.08)','icon'=>'bi-hourglass-split'],
                                    ];
                                @endphp
                                @foreach(['active','free','inactive','pending'] as $st)
                                @php $tc = $tileConfig[$st]; $bd = $billInformationData['by_status'][$st]; @endphp
                                <div class="col-6 p-2" style="border-right:{{ $loop->odd ? '1px solid rgba(0,0,0,0.06)' : 'none' }};border-bottom:{{ $loop->iteration <= 2 ? '1px solid rgba(0,0,0,0.06)' : 'none' }};">
                                    <div class="rounded-3 p-2" style="background:{{ $tc['bg'] }};">
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <i class="bi {{ $tc['icon'] }}" style="color:{{ $tc['color'] }};font-size:11px;"></i>
                                            <span class="fw-semibold" style="font-size:11px;color:{{ $tc['color'] }};text-transform:capitalize;">{{ __($st) }}</span>
                                        </div>
                                        <div style="font-size:10px;color:#6b7280;">
                                            <div>{{ __('Rent:') }} <strong style="color:#111;">{{ number_format($bd['monthly_rent'],0) }}</strong></div>
                                            <div>{{ __('Adv:') }} <strong style="color:#16a34a;">{{ number_format($bd['advance'],0) }}</strong></div>
                                            <div>{{ __('Due:') }} <strong style="color:#dc2626;">{{ number_format(abs($bd['due_amount']),0) }}</strong></div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-12 col-lg-12 col-xxxl-12 d-flex flex-fill">
            <div class="card border-0 shadow-sm rounded-4 w-100 overflow-hidden d-flex flex-column" style="min-height: 460px; border: 1px solid rgba(0,0,0,0.08); background: var(--bs-card-bg);">
                <div class="card-header py-3 border-0 text-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-graph-up-arrow text-danger me-2"></i>{{ __('Income & Revenue Overview') }}</h6>
                </div>
                <div class="card-body p-3" id="income_revenue"></div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            document.addEventListener('livewire:navigated', function () {
                requestAnimationFrame(() => {
                    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

                    // for destroying existing charts
                    if (window.chart1) chart1.destroy();
                    if (window.chart2) chart2.destroy();
                    if (window.chart2b) chart2b.destroy();
                    if (window.chart3) chart3.destroy();

                    // ✅ 1st chart: customers
                    const customersEl = document.querySelector("#customers");
                    if (customersEl) {
                        const customersData = @json(array_values($customersData));
                        const customers = {
                            series: customersData,
                            chart: {
                                height: 360,
                                type: 'radialBar',
                            },
                            theme: {
                                mode: isDark ? 'dark' : 'light'
                            },
                            plotOptions: {
                                radialBar: {
                                    offsetY: 0,
                                    startAngle: 0,
                                    endAngle: 270,
                                    hollow: {
                                        margin: 5,
                                        size: '30%',
                                        background: 'transparent',
                                    },
                                    dataLabels: {
                                        name: { show: false },
                                        value: { show: false }
                                    },
                                    barLabels: {
                                        enabled: true,
                                        useSeriesColors: true,
                                        offsetX: -8,
                                        fontSize: '16px',
                                        formatter: function (seriesName, opts) {
                                            return seriesName + ":  " + opts.w.globals.series[opts.seriesIndex]
                                        },
                                    },
                                }
                            },
                            labels: ['{{ __("Total") }}', '{{ __("Active") }}', '{{ __("Pending") }}', '{{ __("Free") }}', '{{ __("Temporary Disable") }}', '{{ __("Inactive") }}', '{{ __("Recent") }}']
                        };
                        window.chart1 = new ApexCharts(customersEl, customers);
                        chart1.render();
                    }

                    // ✅ 2a chart: billInformation — Global Summary (all 8 metrics)
                    const billEl = document.querySelector("#billInformation");
                    if (billEl) {
                        const billData = @json(array_values(array_filter(
                            $billInformationData,
                            fn($k) => $k !== 'by_status',
                            ARRAY_FILTER_USE_KEY
                        )));
                        const billInformation = {
                            series: [{ name: 'Amount', data: billData }],
                            chart: {
                                type: 'bar',
                                height: 340,
                                toolbar: { show: false },
                                fontFamily: 'inherit',
                                animations: { enabled: true, easing: 'easeinout', speed: 600 },
                            },
                            theme: {
                                mode: isDark ? 'dark' : 'light'
                            },
                            plotOptions: {
                                bar: {
                                    distributed: true,
                                    columnWidth: '55%',
                                    borderRadius: 5,
                                    borderRadiusApplication: 'end',
                                    dataLabels: { position: 'top' },
                                },
                            },
                            legend: { show: false },
                            colors: ['#16a34a','#ef4444','#4f46e5','#0ea5e9','#38bdf8','#f59e0b','#fb923c','#dc2626'],
                            dataLabels: {
                                enabled: true,
                                formatter: val => val !== 0 ? (Math.abs(val) >= 1000 ? (Math.abs(val)/1000).toFixed(1)+'k' : Math.abs(val).toFixed(0)) : '',
                                offsetY: -20,
                                style: { fontSize: '10px', fontWeight: 700, colors: [isDark ? '#f8fafc' : '#374151'] },
                            },
                            yaxis: {
                                labels: {
                                    formatter: val => val >= 1000 ? (val/1000).toFixed(1)+'k' : val.toFixed(0),
                                    style: { fontSize: '11px', colors: '#9ca3af' },
                                },
                            },
                            xaxis: {
                                categories: [
                                    '{{ __("Monthly Rent") }}',
                                    '{{ __("Previous Due") }}',
                                    '{{ __("Advance") }}',
                                    '{{ __("Total PPPoE") }}',
                                    '{{ __("Today PPPoE") }}',
                                    '{{ __("Hotspot Total") }}',
                                    '{{ __("Hotspot Today") }}',
                                    '{{ __("Total Due") }}'
                                ],
                                labels: {
                                    style: { fontSize: '11px', fontWeight: 600,
                                        colors: ['#16a34a','#ef4444','#4f46e5','#0ea5e9','#38bdf8','#f59e0b','#fb923c','#dc2626'] },
                                    rotate: -30,
                                    rotateAlways: false,
                                    trim: true,
                                },
                                axisBorder: { show: false },
                                axisTicks: { show: false },
                            },
                            grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
                            tooltip: {
                                y: { formatter: val => '৳ ' + Math.abs(val).toLocaleString(undefined, {minimumFractionDigits:2}) },
                            },
                        };
                        window.chart2 = new ApexCharts(billEl, billInformation);
                        chart2.render();
                    }

                    // ✅ 2b chart: billByStatus — Per-status grouped chart
                    if (window.chart2b) chart2b.destroy();
                    const billStatusEl = document.querySelector("#billByStatus");
                    if (billStatusEl) {
                        const byStatus = @json($billInformationData['by_status'] ?? []);
                        const statusKeys   = ['active', 'free', 'inactive', 'pending'];
                        const statusLabels = ['{{ __("Active") }}', '{{ __("Free") }}', '{{ __("Inactive") }}', '{{ __("Pending") }}'];

                        const rentData = statusKeys.map(s => byStatus[s]?.monthly_rent ?? 0);
                        const advData  = statusKeys.map(s => byStatus[s]?.advance ?? 0);
                        const dueData  = statusKeys.map(s => Math.abs(byStatus[s]?.due_amount ?? 0));

                        const billByStatus = {
                            series: [
                                { name: '{{ __("Monthly Rent") }}', data: rentData },
                                { name: '{{ __("Advance") }}',      data: advData  },
                                { name: '{{ __("Due Amount") }}',   data: dueData  },
                            ],
                            chart: {
                                type: 'bar',
                                height: 260,
                                toolbar: { show: false },
                                fontFamily: 'inherit',
                                animations: { enabled: true, easing: 'easeinout', speed: 600 },
                            },
                            theme: {
                                mode: isDark ? 'dark' : 'light'
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: false,
                                    columnWidth: '65%',
                                    borderRadius: 5,
                                    borderRadiusApplication: 'end',
                                    dataLabels: { position: 'top' },
                                },
                            },
                            colors: ['#16a34a', '#4f46e5', '#dc2626'],
                            dataLabels: {
                                enabled: true,
                                formatter: val => val > 0 ? val.toLocaleString(undefined,{maximumFractionDigits:0}) : '',
                                offsetY: -18,
                                style: { fontSize: '9px', fontWeight: 600, colors: [isDark ? '#f8fafc' : '#374151'] },
                            },
                            stroke: { show: true, width: 2, colors: ['transparent'] },
                            xaxis: {
                                categories: statusLabels,
                                labels: {
                                    style: { fontSize: '12px', fontWeight: 600,
                                        colors: ['#16a34a','#4f46e5','#dc2626','#ea580c'] },
                                },
                                axisBorder: { show: false },
                                axisTicks: { show: false },
                            },
                            yaxis: {
                                labels: {
                                    formatter: val => val >= 1000 ? (val/1000).toFixed(1)+'k' : val,
                                    style: { fontSize: '11px', colors: '#9ca3af' },
                                },
                            },
                            legend: {
                                position: 'top',
                                horizontalAlign: 'center',
                                fontSize: '11px',
                                markers: { width: 10, height: 10, radius: 3 },
                                itemMargin: { horizontal: 6 },
                            },
                            grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
                            tooltip: {
                                shared: true,
                                intersect: false,
                                y: { formatter: val => '৳ ' + val.toLocaleString(undefined,{minimumFractionDigits:2}) },
                            },
                        };
                        window.chart2b = new ApexCharts(billStatusEl, billByStatus);
                        chart2b.render();
                    }

                    // ✅ 3rd chart: income_revenue
                    const revenueEl = document.querySelector("#income_revenue");
                    if (revenueEl) {
                        const chartData = {!! json_encode($results) !!};
                        const cashflowData = Object.values(chartData).map(item => item.cashflow_previous_year);
                        const incomeData = Object.values(chartData).map(item => item.income_current_year);
                        const revenueData = Object.values(chartData).map(item => item.revenue_difference);

                        const income_revenue = {
                            chart: { height: 350, type: "line", stacked: false },
                            theme: {
                                mode: isDark ? 'dark' : 'light'
                            },
                            dataLabels: { enabled: false },
                            stroke: { width: [1, 1, 4] },
                            title: {
                                text: '{{ __("Income Revenue Analysis (:prev - :curr)", ["prev" => now()->subYear()->year, "curr" => now()->year]) }}',
                                align: 'left',
                                offsetX: 60
                            },
                            series: [
                                { name: '{{ __("Income In Previous Year") }}', type: 'column', data: cashflowData },
                                { name: "{{ __('Income In Current Year') }}", type: 'column', data: incomeData },
                                { name: "{{ __('Revenue Difference') }}", type: 'line', data: revenueData },
                            ],
                            xaxis: {
                                categories: [
                                    "{{ __('January') }}", "{{ __('February') }}", "{{ __('March') }}", "{{ __('April') }}", "{{ __('May') }}", "{{ __('June') }}",
                                    "{{ __('July') }}", "{{ __('August') }}", "{{ __('September') }}", "{{ __('October') }}", "{{ __('November') }}", "{{ __('December') }}"
                                ]
                            },
                            yaxis: [
                                {
                                    seriesName: "{{ __('Income In Current Year') }}",
                                    axisTicks: { show: true },
                                    axisBorder: { show: true, color: '#008FFB' },
                                    labels: { style: { colors: '#008FFB' } },
                                    title: {
                                        text: "{{ __('Income In Year :year', ['year' => now()->subYear()->year]) }}",
                                        style: { color: '#008FFB' }
                                    }
                                },
                                {
                                    seriesName: '{{ __("Income In Previous Year") }}',
                                    opposite: true,
                                    axisTicks: { show: true },
                                    axisBorder: { show: true, color: '#00E396' },
                                    labels: { style: { colors: '#00E396' } },
                                    title: {
                                        text: "{{ __('Income In Year :year', ['year' => now()->year]) }}",
                                        style: { color: '#00E396' }
                                    }
                                },
                                {
                                    opposite: true,
                                    seriesName: "{{ __('Revenue Difference') }}",
                                    axisTicks: { show: true },
                                    axisBorder: { show: true, color: '#FEB019' },
                                    labels: { style: { colors: '#FEB019' } },
                                    title: {
                                        text: "{{ __('Revenue Difference') }}",
                                        style: { color: '#FEB019' }
                                    }
                                }
                            ],
                            tooltip: {
                                shared: false,
                                intersect: true,
                                x: { show: false }
                            },
                            legend: {
                                horizontalAlign: "center",
                                offsetX: 40
                            }
                        };
                        window.chart3 = new ApexCharts(revenueEl, income_revenue);
                        chart3.render();
                    }

                    // Live theme update listener for ApexCharts
                    const updateChartsTheme = () => {
                        const activeTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
                        const labelColor = activeTheme === 'dark' ? '#f8fafc' : '#374151';

                        if (window.chart1) window.chart1.updateOptions({ theme: { mode: activeTheme } });
                        if (window.chart2) {
                            window.chart2.updateOptions({
                                theme: { mode: activeTheme },
                                dataLabels: { style: { colors: [labelColor] } }
                            });
                        }
                        if (window.chart2b) {
                            window.chart2b.updateOptions({
                                theme: { mode: activeTheme },
                                dataLabels: { style: { colors: [labelColor] } }
                            });
                        }
                        if (window.chart3) window.chart3.updateOptions({ theme: { mode: activeTheme } });
                    };

                    if (window.themeObserver) window.themeObserver.disconnect();
                    window.themeObserver = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.attributeName === 'data-bs-theme') {
                                updateChartsTheme();
                            }
                        });
                    });
                    window.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

                    // ✅ 4th: initUptimeClocks
                    if (window.uptimeInterval) clearInterval(window.uptimeInterval);
                    window.uptimeInterval = setInterval(() => {
                        document.querySelectorAll('.uptime-clock').forEach(clock => {
                            let uptime = clock.getAttribute('data-uptime');
                            if (!uptime || uptime === 'N/A') return;

                            // Parse MikroTik uptime (e.g., 1w2d3h4m5s)
                            const regex = /(?:(\d+)w)?(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/;
                            const matches = uptime.match(regex);

                            let w = parseInt(matches[1]) || 0;
                            let d = parseInt(matches[2]) || 0;
                            let h = parseInt(matches[3]) || 0;
                            let m = parseInt(matches[4]) || 0;
                            let s = parseInt(matches[5]) || 0;

                            s++;
                            if (s >= 60) { s = 0; m++; }
                            if (m >= 60) { m = 0; h++; }
                            if (h >= 24) { h = 0; d++; }
                            if (d >= 7) { d = 0; w++; }

                            // Rebuild raw data
                            let newRaw = (w ? w+'w' : '') + (d ? d+'d' : '') + (h ? h+'h' : '') + (m ? m+'m' : '') + s + 's';
                            clock.setAttribute('data-uptime', newRaw);

                            // Rebuild display string
                            let display = (w ? w+'w ' : '') + (d ? d+'d ' : '') + (h ? h+'h ' : '') + (m ? m+'m ' : '') + s + 's';
                            clock.innerText = display;
                        });
                    }, 1000);
                });
            });
        </script>
    @endpush

    @push('styles')
        <style>
            .card:hover {
                transform: translateY(-5px);
                transition: all 0.3s ease;
                box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            }

            /* Dark Mode Support for Router Overview Cards */
            .router-overview-card {
                background-color: #ffffff;
                border: 1px solid rgba(0, 0, 0, 0.08) !important;
            }
            .router-card-subheader {
                background-color: #ffffff;
                border-bottom-color: rgba(0, 0, 0, 0.08) !important;
            }
            .router-accordion-button {
                background-color: #ffffff !important;
                color: var(--bs-body-color) !important;
            }
            .router-accordion-body {
                background-color: #ffffff !important;
                border-top-color: rgba(0, 0, 0, 0.05) !important;
            }

            /* Dark Mode overrides */
            [data-bs-theme="dark"] .router-overview-card {
                background-color: #1e293b !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .router-card-body.bg-light {
                background-color: #0f172a !important;
            }
            [data-bs-theme="dark"] .router-card-subheader {
                background-color: #1e293b !important;
                border-bottom-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .router-overview-card .text-dark {
                color: #f1f5f9 !important;
            }
            [data-bs-theme="dark"] .router-overview-card .progress {
                background-color: rgba(255, 255, 255, 0.1) !important;
            }
            [data-bs-theme="dark"] .router-accordion-button {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
            }
            [data-bs-theme="dark"] .router-accordion-body {
                background-color: #1e293b !important;
                color: #cbd5e1 !important;
                border-top-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .router-overview-card .accordion-button:not(.collapsed) {
                background-color: #0f172a !important;
                color: #38bdf8 !important;
                box-shadow: none;
            }
            [data-bs-theme="dark"] .router-overview-card .accordion-button::after {
                filter: invert(1) grayscale(1);
            }

            /* General card border support in dark mode */
            [data-bs-theme="dark"] .card {
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .border-end {
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .border-top {
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .table-light {
                --bs-table-bg: #1e293b;
                --bs-table-color: #f1f5f9;
            }
        </style>
    @endpush

</x-app-layout>
