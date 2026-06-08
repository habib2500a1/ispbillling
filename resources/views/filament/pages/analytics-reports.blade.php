@php
    $report = $this->getReportData();
    $summary = $report['summary'];
    $tabs = $this->getTabDefinitions();
    $domainTabs = [
        'revenue' => ['label' => 'Revenue & Collection', 'keys' => ['collection', 'due', 'revenue']],
        'customers' => ['label' => 'Customers', 'keys' => ['growth', 'churn', 'packages']],
        'network' => ['label' => 'Network', 'keys' => ['online']],
        'gis' => ['label' => 'GIS & Areas', 'keys' => ['area']],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-bi-page">
        <section class="isp-bi-hero">
            <div>
                <p class="isp-bi-hero__eyebrow">Analytics dashboard</p>
                <h1 class="isp-bi-hero__title">Reporting &amp; analytics</h1>
                <p class="isp-bi-hero__sub">
                    {{ $report['from']->format('d M Y') }} – {{ $report['to']->format('d M Y') }}
                    · Same calculations and data as before — redesigned for faster decisions.
                </p>
            </div>
            <div class="isp-bi-hero__score">
                <span>Collection rate</span>
                <strong>{{ $summary['collection_rate'] }}%</strong>
            </div>
        </section>

        <div class="isp-bi-toolbar">
            <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="isp-bi-back">← Intelligence center</a>
            <a href="{{ \App\Filament\Pages\PrintReportsHub::getUrl() }}" class="isp-bi-back">Export center →</a>
        </div>

        <div class="isp-bi-filters">
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="applyDatePreset('today')" class="isp-bi-preset">Today</button>
                <button type="button" wire:click="applyDatePreset('week')" class="isp-bi-preset">This week</button>
                <button type="button" wire:click="applyDatePreset('month')" class="isp-bi-preset">This month</button>
                <button type="button" wire:click="applyDatePreset('year')" class="isp-bi-preset">This year</button>
            </div>
            <div class="flex-1 min-w-[14rem]">
                {{ $this->form }}
            </div>
        </div>

        <div class="isp-bi-mobile-summary">
            <x-isp.reports.kpi-card label="Collected" :value="number_format($summary['collected'], 0).' BDT'" tone="emerald" />
            <x-isp.reports.kpi-card label="Outstanding" :value="number_format($summary['outstanding'], 0).' BDT'" tone="rose" />
            <x-isp.reports.kpi-card label="Active" :value="number_format($summary['active_subscribers'])" tone="sky" />
            <x-isp.reports.kpi-card label="Online" :value="number_format($summary['online_now'])" tone="violet" />
        </div>

        <div class="isp-bi-kpi-grid isp-bi-kpi-grid--4 hidden sm:grid">
            <x-isp.reports.kpi-card label="Collected" :value="number_format($summary['collected'], 2).' BDT'" :hint="$summary['collection_rate'].'% of invoiced'" tone="emerald" />
            <x-isp.reports.kpi-card label="Outstanding" :value="number_format($summary['outstanding'], 2).' BDT'" hint="Open invoice balances" tone="rose" />
            <x-isp.reports.kpi-card label="Active / online" :value="$summary['active_subscribers'].' / '.$summary['online_now']" hint="Subscribers / PPP sessions" tone="sky" />
            <x-isp.reports.kpi-card label="New / churned" :value="'+'.$summary['new_subscribers'].' / −'.$summary['churned']" hint="Selected period" tone="amber" />
        </div>

        <section class="isp-bi-section">
            <div class="isp-bi-section__head">
                <div>
                    <h2 class="isp-bi-section__title">Analytics views</h2>
                    <p class="isp-bi-section__desc">Domain navigation — duplicate data merged visually, exports link to full reports</p>
                </div>
            </div>
            <div class="isp-bi-section__body space-y-3">
                <div class="isp-bi-tabs" role="tablist">
                    @foreach($domainTabs as $domainKey => $domain)
                        <button
                            type="button"
                            role="tab"
                            wire:click="setActiveDomain('{{ $domainKey }}')"
                            class="isp-bi-tab {{ $activeDomain === $domainKey ? 'is-active' : '' }}"
                        >
                            {{ $domain['label'] }}
                        </button>
                    @endforeach
                </div>

                <div class="isp-bi-subtabs" role="tablist">
                    @foreach($domainTabs[$activeDomain]['keys'] as $tabKey)
                        @php $tab = $tabs[$tabKey]; @endphp
                        <button
                            type="button"
                            role="tab"
                            wire:click="setActiveTab('{{ $tabKey }}')"
                            class="isp-bi-subtab {{ $activeTab === $tabKey ? 'is-active' : '' }}"
                        >
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                    @if($tabs[$activeTab]['export_url'])
                        <a href="{{ $tabs[$activeTab]['export_url'] }}" class="isp-bi-subtab ml-auto" style="text-decoration:none">
                            ↓ {{ $tabs[$activeTab]['export_label'] }}
                        </a>
                    @endif
                </div>
            </div>
        </section>

        @if($activeTab === 'collection')
            @php $col = $report['collection']; @endphp
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Collection report</h2>
                        <p class="isp-bi-section__desc">Total {{ number_format($col['total'], 2) }} BDT · by method and day</p>
                    </div>
                    <a href="{{ \App\Filament\Pages\PaymentsReport::getUrl() }}" class="isp-bi-back">Full report →</a>
                </div>
                <div class="isp-bi-section__body isp-bi-split isp-bi-split--2">
                    <div>
                        <h3 class="text-sm font-semibold mb-2">By payment method</h3>
                        <div class="isp-bi-table-wrap">
                            <table class="isp-bi-table">
                                <thead><tr><th>Method</th><th>Count</th><th class="num">Amount</th></tr></thead>
                                <tbody>
                                @forelse($col['by_method'] as $row)
                                    <tr><td class="capitalize">{{ $row['method'] }}</td><td>{{ $row['count'] }}</td><td class="num">{{ number_format($row['amount'], 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="isp-bi-empty">No payments in range</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold mb-2">Collection trends (daily)</h3>
                        <div class="isp-bi-table-wrap max-h-80 overflow-y-auto">
                            <table class="isp-bi-table">
                                <thead><tr><th>Date</th><th>Count</th><th class="num">Amount</th></tr></thead>
                                <tbody>
                                @forelse($col['by_day'] as $row)
                                    <tr><td>{{ $row['date'] }}</td><td>{{ $row['count'] }}</td><td class="num">{{ number_format($row['amount'], 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="isp-bi-empty">No data</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if($activeTab === 'due')
            @php $dueRows = $report['due']; $dueTotal = collect($dueRows)->sum('balance_due'); @endphp
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Due collection</h2>
                        <p class="isp-bi-section__desc">Preview (200 rows) · Total due {{ number_format($dueTotal, 2) }} BDT</p>
                    </div>
                    <a href="{{ \App\Filament\Pages\DueReportProPage::getUrl() }}" class="isp-bi-back">Due pro + aging →</a>
                </div>
                <div class="isp-bi-section__body isp-bi-table-wrap">
                    <table class="isp-bi-table">
                        <thead><tr><th>Invoice</th><th>Customer</th><th>Area</th><th>Due date</th><th>Overdue</th><th class="num">Balance</th></tr></thead>
                        <tbody>
                        @forelse($dueRows as $row)
                            <tr>
                                <td class="font-mono text-xs">{{ $row['invoice_number'] }}</td>
                                <td>{{ $row['customer'] }} <span class="text-gray-400">({{ $row['customer_code'] }})</span></td>
                                <td>{{ $row['area'] }}</td>
                                <td>{{ $row['due_date'] ?? '—' }}</td>
                                <td class="{{ $row['days_overdue'] > 0 ? 'danger' : '' }}">{{ $row['days_overdue'] }}</td>
                                <td class="num font-medium">{{ number_format($row['balance_due'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="isp-bi-empty">No outstanding invoices</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($activeTab === 'revenue')
            @php $rev = $report['revenue']; @endphp
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Revenue analytics (12 months)</h2>
                        <p class="isp-bi-section__desc">Invoiced {{ number_format($rev['totals']['invoiced'], 2) }} · Collected {{ number_format($rev['totals']['collected'], 2) }} BDT</p>
                    </div>
                    <a href="{{ \App\Filament\Pages\BillingReports::getUrl() }}" class="isp-bi-back">Monthly widgets →</a>
                </div>
                <div class="isp-bi-section__body">
                    <div class="isp-bi-chart"><canvas id="revenueChart" wire:ignore></canvas></div>
                </div>
            </section>
            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    const el = document.getElementById('revenueChart');
                    if (!el || typeof Chart === 'undefined') return;
                    if (el._chart) { el._chart.destroy(); }
                    el._chart = new Chart(el, {
                        type: 'bar',
                        data: {
                            labels: @json($rev['labels']),
                            datasets: [
                                { label: 'Invoiced', data: @json($rev['invoiced']), backgroundColor: 'rgba(99, 102, 241, 0.75)', borderRadius: 6 },
                                { label: 'Collected', data: @json($rev['collected']), backgroundColor: 'rgba(16, 185, 129, 0.75)', borderRadius: 6 },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 600, easing: 'easeOutQuart' },
                            plugins: { legend: { position: 'bottom' } },
                            scales: { y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' } }, x: { grid: { display: false } } },
                        },
                    });
                })();
            </script>
            @endpush
        @endif

        @if($activeTab === 'churn')
            @php $churn = $report['churn']; @endphp
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Churn statistics</h2>
                        <p class="isp-bi-section__desc">Status breakdown and churned subscribers in period</p>
                    </div>
                    <a href="{{ \App\Filament\Pages\ChurnZoneReports::getUrl() }}" class="isp-bi-back">Zone churn →</a>
                </div>
                <div class="isp-bi-section__body">
                    <div class="isp-bi-chips mb-4">
                        @foreach($churn['by_status'] as $s)
                            <span class="isp-bi-chip">{{ $s['status'] }}: <strong>{{ $s['count'] }}</strong></span>
                        @endforeach
                    </div>
                    <div class="isp-bi-table-wrap">
                        <table class="isp-bi-table">
                            <thead><tr><th>Code</th><th>Name</th><th>Status</th><th>Package</th><th>Updated</th></tr></thead>
                            <tbody>
                            @forelse($churn['churned'] as $row)
                                <tr><td>{{ $row['customer_code'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['package'] }}</td><td>{{ $row['updated_at'] }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="isp-bi-empty">No churn in period</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        @if($activeTab === 'growth')
            @php $growth = $report['growth']; @endphp
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Customer growth</h2>
                        <p class="isp-bi-section__desc">New registrations vs active subscriber base (12 months)</p>
                    </div>
                </div>
                <div class="isp-bi-section__body">
                    <div class="isp-bi-chart"><canvas id="growthChart" wire:ignore></canvas></div>
                </div>
            </section>
            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    const el = document.getElementById('growthChart');
                    if (!el || typeof Chart === 'undefined') return;
                    if (el._chart) { el._chart.destroy(); }
                    el._chart = new Chart(el, {
                        type: 'line',
                        data: {
                            labels: @json($growth['labels']),
                            datasets: [
                                { label: 'New subscribers', data: @json($growth['new_subscribers']), borderColor: 'rgb(16, 185, 129)', backgroundColor: 'rgba(16, 185, 129, 0.12)', fill: true, tension: 0.35 },
                                { label: 'Active total', data: @json($growth['total_active']), borderColor: 'rgb(99, 102, 241)', backgroundColor: 'rgba(99, 102, 241, 0.08)', fill: true, tension: 0.35 },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 600, easing: 'easeOutQuart' },
                            plugins: { legend: { position: 'bottom' } },
                            scales: { y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' } }, x: { grid: { display: false } } },
                        },
                    });
                })();
            </script>
            @endpush
        @endif

        @if($activeTab === 'online')
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Network analytics — online users</h2>
                        <p class="isp-bi-section__desc">{{ count($report['online']) }} active PPP sessions (router-linked)</p>
                    </div>
                    <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="isp-bi-back">Live monitor →</a>
                </div>
                <div class="isp-bi-section__body isp-bi-table-wrap">
                    <table class="isp-bi-table">
                        <thead><tr><th>Customer</th><th>Username</th><th>Area</th><th>Package</th><th>IP</th><th>Download</th><th>Upload</th><th>Started</th></tr></thead>
                        <tbody>
                        @forelse($report['online'] as $row)
                            <tr>
                                <td>{{ $row['customer'] }} <span class="text-gray-400">({{ $row['code'] }})</span></td>
                                <td class="font-mono text-xs">{{ $row['username'] }}</td>
                                <td>{{ $row['area'] }}</td>
                                <td>{{ $row['package'] }}</td>
                                <td>{{ $row['ip'] }}</td>
                                <td>{{ \App\Filament\Pages\AnalyticsReports::formatBps($row['download']) }}</td>
                                <td>{{ \App\Filament\Pages\AnalyticsReports::formatBps($row['upload']) }}</td>
                                <td>{{ $row['started_at'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="isp-bi-empty">No users online</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($activeTab === 'area')
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">GIS &amp; area analytics</h2>
                        <p class="isp-bi-section__desc">Area-wise customers, revenue, and outstanding — links to fiber GIS map</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ \App\Filament\Pages\FiberPlantMap::getUrl() }}" class="isp-bi-back">Fiber map →</a>
                        <a href="{{ \App\Filament\Pages\AreaWiseClientsReport::getUrl() }}" class="isp-bi-back">CSV export →</a>
                    </div>
                </div>
                <div class="isp-bi-section__body isp-bi-table-wrap">
                    <table class="isp-bi-table">
                        <thead><tr><th>Area</th><th>Code</th><th>Customers</th><th>Active</th><th class="num">Collected MTD</th><th class="num">Outstanding</th></tr></thead>
                        <tbody>
                        @foreach($report['area'] as $row)
                            <tr>
                                <td>{{ $row['area'] }}</td><td>{{ $row['code'] }}</td><td>{{ $row['total_customers'] }}</td><td>{{ $row['active'] }}</td>
                                <td class="num">{{ number_format($row['collected_mtd'], 2) }}</td>
                                <td class="num">{{ number_format($row['outstanding'], 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($activeTab === 'packages')
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Package distribution</h2>
                        <p class="isp-bi-section__desc">Popularity and estimated MRR by package</p>
                    </div>
                    <a href="{{ \App\Filament\Pages\PackageWiseReportPage::getUrl() }}" class="isp-bi-back">Full export →</a>
                </div>
                <div class="isp-bi-section__body isp-bi-table-wrap">
                    <table class="isp-bi-table">
                        <thead><tr><th>Package</th><th>Speed</th><th>Price</th><th>Subscribers</th><th>Active</th><th class="num">Est. MRR</th></tr></thead>
                        <tbody>
                        @forelse($report['packages'] as $row)
                            <tr>
                                <td class="font-medium">{{ $row['package'] }}</td>
                                <td>{{ $row['speed'] }}</td>
                                <td>{{ number_format($row['price'], 2) }}</td>
                                <td>{{ $row['subscribers'] }}</td>
                                <td>{{ $row['active'] }}</td>
                                <td class="num">{{ number_format($row['est_mrr'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="isp-bi-empty">No active packages</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
