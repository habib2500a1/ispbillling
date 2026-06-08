<x-filament-panels::page class="isp-fin-page isp-hub-page">
    <link rel="stylesheet" href="{{ asset('css/finance-hub.css') }}?v={{ @filemtime(public_path('css/finance-hub.css')) ?: 1 }}">
    <script src="{{ asset('js/finance-hub.js') }}?v={{ @filemtime(public_path('js/finance-hub.js')) ?: 1 }}" defer data-cfasync="false"></script>

    <div class="space-y-5">
        {{-- Hero --}}
        <section class="isp-fin-hero isp-fin-glass">
            <p class="text-xs uppercase tracking-wider opacity-80 mb-1">ISP Finance Operations</p>
            <h1 class="isp-fin-hero__title">Finance Operations Center</h1>
            <p class="isp-fin-hero__sub">
                Revenue · collections · GL · cashbook · expenses · P&amp;L — {{ $finance['period_label'] ?? now()->format('F Y') }}
            </p>
            <div class="isp-fin-search" wire:ignore.self>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search customer, invoice, payment, vendor, account, expense…"
                    autocomplete="off"
                    aria-label="Global finance search"
                >
                @if (strlen($searchQuery) >= 2)
                    <div class="isp-fin-search-results">
                        @forelse ($searchResults as $row)
                            <a href="{{ $row['url'] ?? '#' }}">
                                <strong>{{ $row['label'] }}</strong>
                                <span class="block opacity-70">{{ ucfirst($row['type']) }} · {{ $row['meta'] ?? '' }}</span>
                            </a>
                        @empty
                            <p class="p-3 text-sm opacity-70">No matches</p>
                        @endforelse
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap gap-2 mt-3 relative z-10">
                <span class="isp-fin-pill">{{ $gl['accounts'] ?? 0 }} GL accounts</span>
                <span class="isp-fin-pill">{{ $gl['journals'] ?? 0 }} journals MTD</span>
                <span class="isp-fin-pill">{{ $gl['banks'] ?? 0 }} banks · {{ $gl['vendors'] ?? 0 }} vendors</span>
                <span class="isp-fin-pill isp-fin-pill--accent">{{ $kpis['collection_efficiency'] ?? 0 }}% collection efficiency</span>
            </div>
        </section>

        {{-- KPI grid --}}
        <div class="isp-fin-kpi-grid">
            @foreach ($kpiCards as $card)
                <div class="isp-fin-kpi isp-fin-glass {{ $card['class'] }}">
                    <span>{{ $card['label'] }}</span>
                    <strong data-fin-kpi="{{ (float) ($kpis[$card['key']] ?? 0) }}">{{ number_format($kpis[$card['key']] ?? 0, 0) }}</strong>
                    <em>BDT</em>
                </div>
            @endforeach
        </div>

        {{-- Income vs expense bar --}}
        <section class="isp-fin-glass p-4">
            <div class="flex items-center justify-between text-xs opacity-75 mb-2">
                <span>Expenses {{ number_format($accounts['expenses'] ?? 0, 0) }} BDT</span>
                <span>Income {{ number_format($accounts['income'] ?? 0, 0) }} BDT · Margin {{ $finance['profit_margin'] ?? 0 }}%</span>
            </div>
            <div class="isp-fin-progress">
                <div class="isp-fin-progress__income" style="width: {{ $finance['income_pct'] ?? 50 }}%"></div>
            </div>
        </section>

        {{-- Tabs --}}
        <div class="isp-fin-tabs">
            @foreach (['dashboard' => 'Dashboard', 'accounting' => 'Accounting', 'reports' => 'Reports', 'analytics' => 'ISP analytics'] as $tab => $label)
                <button type="button" wire:click="setTab('{{ $tab }}')" class="isp-fin-tab {{ $activeTab === $tab ? 'isp-fin-tab--active' : '' }}">{{ $label }}</button>
            @endforeach
            <button type="button" wire:click="refreshFinance" class="isp-fin-tab ml-auto" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="refreshFinance">↻ Refresh</span>
                <span wire:loading wire:target="refreshFinance">…</span>
            </button>
        </div>

        <div class="isp-fin-layout">
            <nav class="isp-fin-layout__nav isp-fin-glass p-3">
                <p class="text-xs uppercase opacity-60 mb-2 px-1">Accounting modules</p>
                @foreach ($navLinks as $link)
                    <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <main class="space-y-4">
                @if ($activeTab === 'dashboard' || $activeTab === 'accounting')
                    <section class="isp-fin-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Quick actions</h2>
                        <div class="isp-fin-quick-grid">
                            @foreach ($quickActions as $action)
                                <a href="{{ $action['url'] }}" class="isp-fin-quick isp-fin-glass isp-fin-quick--{{ $action['tone'] }}">
                                    <x-filament::icon :icon="'heroicon-o-'.$action['icon']" class="h-5 w-5" />
                                    <strong>{{ $action['label'] }}</strong>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($activeTab === 'dashboard')
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section class="isp-fin-glass p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-sm font-semibold">Recent collections</h2>
                                <a href="{{ \App\Filament\Resources\PaymentResource::getUrl('index') }}" class="text-xs text-primary-600">View all →</a>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="isp-fin-table">
                                    <thead>
                                        <tr><th>Receipt</th><th>Customer</th><th>Amount</th><th>Method</th><th>Time</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($finance['recent_payments'] ?? [] as $pay)
                                            <tr>
                                                <td>{{ $pay['receipt'] }}</td>
                                                <td>{{ $pay['customer'] ?? '—' }}</td>
                                                <td class="tabular-nums">{{ number_format($pay['amount'], 0) }}</td>
                                                <td>{{ $pay['method'] }}</td>
                                                <td>{{ $pay['at'] }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="opacity-60">No payments yet</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="isp-fin-glass p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-sm font-semibold">Pending expense approvals</h2>
                                <a href="{{ \App\Filament\Resources\StaffExpenseResource::getUrl('index') }}" class="text-xs text-primary-600">Review →</a>
                            </div>
                            <ul class="isp-fin-list">
                                @forelse ($finance['pending_expenses'] ?? [] as $exp)
                                    <li>
                                        <a href="{{ $exp['url'] }}">
                                            <span>{{ $exp['label'] }}</span>
                                            <strong>{{ number_format($exp['amount'], 0) }} BDT</strong>
                                        </a>
                                    </li>
                                @empty
                                    <li class="opacity-60 text-sm">No pending staff expenses</li>
                                @endforelse
                            </ul>
                        </section>
                    </div>

                    <section class="isp-fin-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Aging snapshot</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach (['current' => 'Current', '1_30' => '1–30 days', '31_60' => '31–60 days', '60_plus' => '60+ days'] as $key => $label)
                                @php $bucket = $finance['ops']['aging'][$key] ?? ['count' => 0, 'amount' => 0]; @endphp
                                <div class="isp-fin-aging">
                                    <span>{{ $label }}</span>
                                    <strong>{{ number_format($bucket['amount'], 0) }}</strong>
                                    <em>{{ $bucket['count'] }} invoices</em>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($activeTab === 'accounting')
                    <div class="isp-fin-modules space-y-6">
                        @foreach ($moduleGroups as $group)
                            <section class="isp-fin-section isp-fin-section--{{ $group['tone'] }}">
                                <header class="isp-fin-section-head">
                                    <span class="isp-fin-section-icon isp-fin-section-icon--{{ $group['tone'] }}">
                                        <x-filament::icon :icon="'heroicon-o-'.$group['icon']" class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <h3 class="isp-fin-section-title">{{ $group['title'] }}</h3>
                                        <p class="text-sm opacity-70">{{ $group['subtitle'] }}</p>
                                    </div>
                                </header>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($group['items'] as $item)
                                        <a href="{{ $item['url'] }}" class="isp-fin-card isp-fin-card--{{ $group['tone'] }} group">
                                            <div class="flex items-start gap-3">
                                                <span class="isp-fin-card-icon isp-fin-card-icon--{{ $group['tone'] }}">
                                                    <x-filament::icon :icon="'heroicon-o-'.$item['icon']" class="h-5 w-5" />
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <p class="font-semibold">{{ $item['title'] }}</p>
                                                        @if ($item['badge'])
                                                            <span class="isp-fin-badge">{{ $item['badge'] }}</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-1 text-sm opacity-70">{{ $item['description'] }}</p>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif

                @if ($activeTab === 'reports')
                    <section class="isp-fin-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Financial reports</h2>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($finance['report_links'] ?? [] as $report)
                                <a href="{{ $report['url'] }}" class="isp-fin-report-card">
                                    <x-filament::icon :icon="'heroicon-o-'.($report['icon'] ?? 'document-chart-bar')" class="h-6 w-6" />
                                    <span>{{ $report['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                        <p class="mt-4 text-xs opacity-60">
                            Balance sheet &amp; cash flow statement open from Financial reports — existing GL logic unchanged.
                        </p>
                    </section>
                @endif

                @if ($activeTab === 'analytics')
                    @php $isp = $finance['isp_analytics'] ?? []; @endphp
                    <div class="grid gap-4 lg:grid-cols-3 mb-4">
                        <div class="isp-fin-analytic-card isp-fin-glass">
                            <span>Collection efficiency</span>
                            <strong>{{ $isp['collection_efficiency'] ?? $kpis['collection_efficiency'] ?? 0 }}%</strong>
                        </div>
                        <div class="isp-fin-analytic-card isp-fin-glass">
                            <span>Avg lifetime value (proxy)</span>
                            <strong>{{ number_format($isp['clv_proxy'] ?? 0, 0) }} BDT</strong>
                        </div>
                        <div class="isp-fin-analytic-card isp-fin-glass">
                            <span>Outstanding due</span>
                            <strong>{{ number_format($kpis['due_collection'] ?? 0, 0) }} BDT</strong>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <section class="isp-fin-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Zone revenue</h2>
                            <table class="isp-fin-table">
                                <thead><tr><th>Zone</th><th>Collected</th><th>Rate</th></tr></thead>
                                <tbody>
                                    @forelse ($isp['zone_revenue'] ?? [] as $row)
                                        <tr>
                                            <td>{{ $row['zone'] ?? '—' }}</td>
                                            <td class="tabular-nums">{{ number_format($row['collected'] ?? 0, 0) }}</td>
                                            <td>{{ $row['collection_rate'] ?? 0 }}%</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="opacity-60">No zone data</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </section>

                        <section class="isp-fin-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Package revenue (MRR)</h2>
                            <table class="isp-fin-table">
                                <thead><tr><th>Package</th><th>Active</th><th>MRR</th></tr></thead>
                                <tbody>
                                    @forelse ($isp['package_revenue'] ?? [] as $row)
                                        <tr>
                                            <td>{{ $row['package'] ?? '—' }}</td>
                                            <td>{{ $row['active'] ?? 0 }}</td>
                                            <td class="tabular-nums">{{ number_format($row['est_mrr'] ?? 0, 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="opacity-60">No packages</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </section>

                        <section class="isp-fin-glass p-4 lg:col-span-2">
                            <h2 class="text-sm font-semibold mb-3">Area / network revenue</h2>
                            <table class="isp-fin-table">
                                <thead><tr><th>Area</th><th>Active subs</th><th>Collected (MTD)</th></tr></thead>
                                <tbody>
                                    @forelse ($isp['area_revenue'] ?? [] as $row)
                                        <tr>
                                            <td>{{ $row['area'] ?? '—' }}</td>
                                            <td>{{ $row['active'] ?? 0 }}</td>
                                            <td class="tabular-nums">{{ number_format($row['collected_mtd'] ?? 0, 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="opacity-60">No area data</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl() }}" class="inline-block mt-3 text-xs text-primary-600">Full analytics →</a>
                        </section>
                    </div>
                @endif
            </main>
        </div>

        {{-- Mobile manager bar --}}
        <nav class="isp-fin-mobile-bar" aria-label="Mobile finance shortcuts">
            <a href="{{ \App\Filament\Pages\BillingOverview::getUrl() }}" title="Revenue">
                <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5" />
                <span>Revenue</span>
            </a>
            <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" title="Collection">
                <x-filament::icon icon="heroicon-o-currency-bangladeshi" class="h-5 w-5" />
                <span>Collect</span>
            </a>
            <a href="{{ \App\Filament\Resources\StaffExpenseResource::getUrl('index') }}" title="Approve expenses">
                <x-filament::icon icon="heroicon-o-check-badge" class="h-5 w-5" />
                <span>Approve</span>
            </a>
            <a href="{{ \App\Filament\Pages\FinancialReports::getUrl() }}" title="Reports">
                <x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5" />
                <span>Reports</span>
            </a>
        </nav>

        <x-isp.hub-footer :links="$footerLinks" />
    </div>
</x-filament-panels::page>
