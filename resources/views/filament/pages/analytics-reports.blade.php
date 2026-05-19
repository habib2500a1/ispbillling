@php
    $report = $this->getReportData();
    $summary = $report['summary'];
    $tabs = [
        'collection' => 'Collection',
        'due' => 'Due',
        'revenue' => 'Revenue',
        'churn' => 'Churn',
        'growth' => 'Growth',
        'online' => 'Online',
        'area' => 'Area-wise',
        'packages' => 'Packages',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="text-sm text-indigo-600 hover:underline">&larr; Reports hub</a>
            <form wire:submit.prevent class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                {{ $this->form }}
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Collected</p>
                <p class="text-xl font-bold text-emerald-600">{{ number_format($summary['collected'], 2) }} BDT</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Outstanding</p>
                <p class="text-xl font-bold text-rose-600">{{ number_format($summary['outstanding'], 2) }} BDT</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Active / online</p>
                <p class="text-xl font-bold">{{ $summary['active_subscribers'] }} / {{ $summary['online_now'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">New / churned</p>
                <p class="text-xl font-bold"><span class="text-emerald-600">+{{ $summary['new_subscribers'] }}</span> <span class="text-rose-600">−{{ $summary['churned'] }}</span></p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
            @foreach($tabs as $key => $label)
                <button
                    type="button"
                    wire:click="setActiveTab('{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $activeTab === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($activeTab === 'collection')
            @php $col = $report['collection']; @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Collection report</h3>
                <p class="text-sm text-gray-500">{{ $report['from']->format('d M Y') }} – {{ $report['to']->format('d M Y') }} · Total {{ number_format($col['total'], 2) }} BDT</p>
                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div>
                        <h4 class="mb-2 font-medium text-gray-700 dark:text-gray-300">By payment method</h4>
                        <table class="w-full text-left text-sm">
                            <thead><tr class="border-b dark:border-gray-700"><th class="py-2">Method</th><th>Count</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                            @forelse($col['by_method'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 capitalize">{{ $row['method'] }}</td><td>{{ $row['count'] }}</td><td class="text-right">{{ number_format($row['amount'], 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="py-4 text-gray-500">No payments in range</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <h4 class="mb-2 font-medium text-gray-700 dark:text-gray-300">By day</h4>
                        <div class="max-h-80 overflow-y-auto">
                            <table class="w-full text-left text-sm">
                                <thead><tr class="border-b dark:border-gray-700"><th class="py-2">Date</th><th>Count</th><th class="text-right">Amount</th></tr></thead>
                                <tbody>
                                @forelse($col['by_day'] as $row)
                                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row['date'] }}</td><td>{{ $row['count'] }}</td><td class="text-right">{{ number_format($row['amount'], 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="py-4 text-gray-500">No data</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($activeTab === 'due')
            @php $dueRows = $report['due']; $dueTotal = collect($dueRows)->sum('balance_due'); @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Due report</h3>
                <p class="text-sm text-gray-500">Open invoices · Total due {{ number_format($dueTotal, 2) }} BDT</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead><tr class="border-b dark:border-gray-700"><th class="py-2">Invoice</th><th>Customer</th><th>Area</th><th>Due date</th><th>Days overdue</th><th class="text-right">Balance</th></tr></thead>
                        <tbody>
                        @forelse($dueRows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 font-mono text-xs">{{ $row['invoice_number'] }}</td>
                                <td>{{ $row['customer'] }} <span class="text-gray-400">({{ $row['customer_code'] }})</span></td>
                                <td>{{ $row['area'] }}</td>
                                <td>{{ $row['due_date'] ?? '—' }}</td>
                                <td class="{{ $row['days_overdue'] > 0 ? 'text-rose-600 font-medium' : '' }}">{{ $row['days_overdue'] }}</td>
                                <td class="text-right font-medium">{{ number_format($row['balance_due'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500">No outstanding invoices</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($activeTab === 'revenue')
            @php $rev = $report['revenue']; @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Revenue analytics (12 months)</h3>
                <p class="text-sm text-gray-500">Invoiced {{ number_format($rev['totals']['invoiced'], 2) }} · Collected {{ number_format($rev['totals']['collected'], 2) }} BDT</p>
                <div class="mt-4" style="height: 320px;"><canvas id="revenueChart"></canvas></div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    const el = document.getElementById('revenueChart');
                    if (!el || typeof Chart === 'undefined') return;
                    new Chart(el, {
                        type: 'bar',
                        data: {
                            labels: @json($rev['labels']),
                            datasets: [
                                { label: 'Invoiced', data: @json($rev['invoiced']), backgroundColor: 'rgba(99, 102, 241, 0.7)' },
                                { label: 'Collected', data: @json($rev['collected']), backgroundColor: 'rgba(16, 185, 129, 0.7)' },
                            ],
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
                    });
                })();
            </script>
        @endif

        @if($activeTab === 'churn')
            @php $churn = $report['churn']; @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Churn analysis</h3>
                <p class="text-sm text-gray-500">{{ $report['from']->format('d M Y') }} – {{ $report['to']->format('d M Y') }}</p>
                <div class="mt-4 flex flex-wrap gap-4">
                    @foreach($churn['by_status'] as $s)
                        <span class="rounded-lg bg-gray-100 px-3 py-2 text-sm dark:bg-gray-800">{{ $s['status'] }}: <strong>{{ $s['count'] }}</strong></span>
                    @endforeach
                </div>
                <table class="mt-6 w-full text-left text-sm">
                    <thead><tr class="border-b dark:border-gray-700"><th class="py-2">Code</th><th>Name</th><th>Status</th><th>Package</th><th>Updated</th></tr></thead>
                    <tbody>
                    @forelse($churn['churned'] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row['customer_code'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['package'] }}</td><td>{{ $row['updated_at'] }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-gray-500">No churn in period</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if($activeTab === 'growth')
            @php $growth = $report['growth']; @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Subscriber growth</h3>
                <div class="mt-4" style="height: 320px;"><canvas id="growthChart"></canvas></div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    const el = document.getElementById('growthChart');
                    if (!el || typeof Chart === 'undefined') return;
                    new Chart(el, {
                        type: 'line',
                        data: {
                            labels: @json($growth['labels']),
                            datasets: [
                                { label: 'New subscribers', data: @json($growth['new_subscribers']), borderColor: 'rgb(16, 185, 129)', tension: 0.3 },
                                { label: 'Active total', data: @json($growth['total_active']), borderColor: 'rgb(99, 102, 241)', tension: 0.3 },
                            ],
                        },
                        options: { responsive: true, maintainAspectRatio: false },
                    });
                })();
            </script>
        @endif

        @if($activeTab === 'online')
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Online user report</h3>
                <p class="text-sm text-gray-500">{{ count($report['online']) }} active PPP sessions</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[800px] text-left text-sm">
                        <thead><tr class="border-b dark:border-gray-700"><th class="py-2">Customer</th><th>Username</th><th>Area</th><th>Package</th><th>IP</th><th>Download</th><th>Upload</th><th>Started</th></tr></thead>
                        <tbody>
                        @forelse($report['online'] as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2">{{ $row['customer'] }} <span class="text-gray-400">({{ $row['code'] }})</span></td>
                                <td class="font-mono text-xs">{{ $row['username'] }}</td>
                                <td>{{ $row['area'] }}</td>
                                <td>{{ $row['package'] }}</td>
                                <td>{{ $row['ip'] }}</td>
                                <td>{{ \App\Filament\Pages\AnalyticsReports::formatBps($row['download']) }}</td>
                                <td>{{ \App\Filament\Pages\AnalyticsReports::formatBps($row['upload']) }}</td>
                                <td>{{ $row['started_at'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-6 text-center text-gray-500">No users online</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($activeTab === 'area')
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Area-wise report</h3>
                <table class="mt-4 w-full text-left text-sm">
                    <thead><tr class="border-b dark:border-gray-700"><th class="py-2">Area</th><th>Code</th><th>Customers</th><th>Active</th><th class="text-right">Collected MTD</th><th class="text-right">Outstanding</th></tr></thead>
                    <tbody>
                    @foreach($report['area'] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2">{{ $row['area'] }}</td><td>{{ $row['code'] }}</td><td>{{ $row['total_customers'] }}</td><td>{{ $row['active'] }}</td>
                            <td class="text-right">{{ number_format($row['collected_mtd'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['outstanding'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($activeTab === 'packages')
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Package popularity</h3>
                <table class="mt-4 w-full text-left text-sm">
                    <thead><tr class="border-b dark:border-gray-700"><th class="py-2">Package</th><th>Speed</th><th>Price</th><th>Subscribers</th><th>Active</th><th class="text-right">Est. MRR</th></tr></thead>
                    <tbody>
                    @forelse($report['packages'] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 font-medium">{{ $row['package'] }}</td>
                            <td>{{ $row['speed'] }}</td>
                            <td>{{ number_format($row['price'], 2) }}</td>
                            <td>{{ $row['subscribers'] }}</td>
                            <td>{{ $row['active'] }}</td>
                            <td class="text-right">{{ number_format($row['est_mrr'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-gray-500">No active packages</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
