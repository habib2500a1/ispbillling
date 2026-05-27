@php
    $stats = $this->getStats();
    $quickActions = $this->getQuickActions();
    $moduleGroups = $this->getModuleGroups();
    $profitPositive = $stats['month_profit'] >= 0;
    $footerLinks = [
        ['url' => \App\Filament\Pages\BillingOverview::getUrl(), 'label' => 'Billing', 'icon' => 'heroicon-o-banknotes'],
        ['url' => \App\Filament\Pages\BillCollectionDesk::getUrl(), 'label' => 'Collect', 'icon' => 'heroicon-o-currency-bangladeshi'],
        ['url' => \App\Filament\Resources\CashbookEntryResource::getUrl('index'), 'label' => 'Cashbook', 'icon' => 'heroicon-o-wallet'],
        ['url' => \App\Filament\Pages\FinancialReports::getUrl(), 'label' => 'Reports', 'icon' => 'heroicon-o-chart-bar'],
        ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-accounting-hub isp-hub-page space-y-6">
        <div class="isp-accounting-hero">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-white/70">Finance command center</p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Accounting & finance</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/85">
                        Cashbook · ledger · bank · vendors · payroll · P&L · VAT — {{ $stats['period_label'] }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="isp-accounting-pill">{{ $stats['accounts'] }} GL accounts</span>
                    <span class="isp-accounting-pill">{{ $stats['journals'] }} journals</span>
                    <span class="isp-accounting-pill">{{ $stats['vendors'] }} vendors · {{ $stats['employees'] }} staff</span>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="isp-accounting-kpi isp-accounting-kpi--cash">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Cash on hand</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-white">{{ number_format($stats['cash_balance'], 0) }} <span class="text-sm font-semibold">BDT</span></p>
                </div>
                <div class="isp-accounting-kpi isp-accounting-kpi--bank">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Bank total</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-white">{{ number_format($stats['bank_balance'], 0) }} <span class="text-sm font-semibold">BDT</span></p>
                    <p class="mt-0.5 text-xs text-white/65">{{ $stats['banks'] }} active accounts</p>
                </div>
                <div class="isp-accounting-kpi isp-accounting-kpi--income">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Month income</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-white">{{ number_format($stats['month_income'], 0) }} <span class="text-sm font-semibold">BDT</span></p>
                </div>
                <div class="isp-accounting-kpi isp-accounting-kpi--{{ $profitPositive ? 'profit' : 'loss' }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Net {{ $profitPositive ? 'profit' : 'loss' }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-white">{{ number_format($stats['month_profit'], 0) }} <span class="text-sm font-semibold">BDT</span></p>
                    <p class="mt-0.5 text-xs text-white/65">Margin {{ $stats['profit_margin'] }}%</p>
                </div>
            </div>

            <div class="mt-5">
                <div class="flex items-center justify-between text-xs text-white/75">
                    <span>Expenses {{ number_format($stats['month_expenses'], 0) }} BDT</span>
                    <span>Income {{ number_format($stats['month_income'], 0) }} BDT</span>
                </div>
                <div class="mt-2 flex h-3 overflow-hidden rounded-full bg-white/15">
                    <div
                        class="h-full rounded-l-full bg-gradient-to-r from-emerald-300 to-teal-400"
                        @style(['width: '.$stats['income_pct'].'%'])
                    ></div>
                    <div class="h-full flex-1 rounded-r-full bg-gradient-to-r from-rose-400 to-orange-400"></div>
                </div>
                <p class="mt-2 text-xs text-white/70">Collections (linked payments): {{ number_format($stats['collections'], 0) }} BDT</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="isp-stat-card border-l-4 border-l-emerald-500">
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Cashbook in</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['cashbook_in'], 0) }} BDT</p>
            </div>
            <div class="isp-stat-card border-l-4 border-l-rose-500">
                <p class="text-xs font-bold uppercase tracking-wide text-rose-600 dark:text-rose-400">Cashbook out</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['cashbook_out'], 0) }} BDT</p>
            </div>
            <div class="isp-stat-card border-l-4 border-l-violet-500">
                <p class="text-xs font-bold uppercase tracking-wide text-violet-600 dark:text-violet-400">Month expenses</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['month_expenses'], 0) }} BDT</p>
            </div>
            <div class="isp-stat-card border-l-4 border-l-cyan-500">
                <p class="text-xs font-bold uppercase tracking-wide text-cyan-600 dark:text-cyan-400">Total liquidity</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['cash_balance'] + $stats['bank_balance'], 0) }} BDT</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cash + bank</p>
            </div>
        </div>

        <div class="isp-accounting-modules space-y-8">
            @foreach ($moduleGroups as $group)
                <section class="isp-accounting-section isp-accounting-section--{{ $group['tone'] }}">
                    <header class="isp-accounting-section-head">
                        <span class="isp-accounting-section-icon isp-accounting-section-icon--{{ $group['tone'] }}">
                            <x-filament::icon :icon="'heroicon-o-'.$group['icon']" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="isp-accounting-section-title">{{ $group['title'] }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $group['subtitle'] }}</p>
                        </div>
                    </header>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($group['items'] as $item)
                            <a href="{{ $item['url'] }}" class="isp-accounting-card isp-accounting-card--{{ $group['tone'] }} group">
                                <div class="flex items-start gap-3">
                                    <span class="isp-accounting-card-icon isp-accounting-card-icon--{{ $group['tone'] }}">
                                        <x-filament::icon :icon="'heroicon-o-'.$item['icon']" class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="font-semibold text-gray-900 group-hover:text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                                            @if ($item['badge'])
                                                <span class="isp-accounting-badge">{{ $item['badge'] }}</span>
                                            @endif
                                        </div>
                                        <p class="mt-1.5 text-sm leading-snug text-gray-600 dark:text-gray-400">{{ $item['description'] }}</p>
                                        <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-gray-500 group-hover:text-gray-800 dark:text-gray-400 dark:group-hover:text-gray-200">
                                            Open
                                            <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <section class="isp-accounting-quick" aria-label="Quick accounting actions">
            <div class="isp-accounting-quick-header">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Quick actions</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">One tap for daily finance tasks</p>
                </div>
                <span class="isp-hub-section__meta">{{ count($quickActions) }} actions</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['url'] }}" class="isp-accounting-quick-btn isp-accounting-quick-btn--{{ $action['tone'] }}" title="{{ $action['label'] }}">
                        <span class="isp-accounting-quick-btn-icon">
                            <x-filament::icon :icon="'heroicon-o-'.$action['icon']" class="h-6 w-6" />
                        </span>
                        <span class="isp-accounting-quick-btn-label">{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <x-isp.hub-footer :links="$footerLinks" />
    </div>
</x-filament-panels::page>
