@php
    $stats = $this->getStats();
    $access = $this->getAccess();
    $quickActions = $this->getQuickActions();
    $moduleGroups = $this->getModuleGroups();
    $footerLinks = [
        ['url' => \App\Filament\Pages\AccountingHub::getUrl(), 'label' => 'Accounting', 'icon' => 'heroicon-o-calculator'],
        ['url' => \App\Filament\Pages\BillingOverview::getUrl(), 'label' => 'Billing', 'icon' => 'heroicon-o-banknotes'],
        ['url' => \App\Filament\Resources\UserResource::getUrl('index'), 'label' => 'Staff', 'icon' => 'heroicon-o-users'],
        ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hr-payroll-hub isp-hub-page space-y-6">
        <div class="isp-hr-payroll-hero">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-white/70">People operations</p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">HR & payroll</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/85">
                        Staff profiles · attendance · salary runs · panel logins & roles — {{ $stats['period_label'] }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="isp-hr-payroll-pill">{{ $stats['active_employees'] }} active staff</span>
                    <span class="isp-hr-payroll-pill">{{ $stats['staff_users'] }} logins</span>
                    <span class="isp-hr-payroll-pill">{{ $stats['attendance_marked_pct'] }}% marked today</span>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="isp-hr-payroll-kpi isp-hr-payroll-kpi--staff">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Active employees</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-white">{{ $stats['active_employees'] }}</p>
                    <p class="mt-0.5 text-xs text-white/65">{{ $stats['total_employees'] }} total in directory</p>
                </div>
                <div class="isp-hr-payroll-kpi isp-hr-payroll-kpi--attendance">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Present today</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-white">{{ $stats['present_today'] }}</p>
                    <p class="mt-0.5 text-xs text-white/65">{{ $stats['absent_today'] }} absent · {{ $stats['leave_today'] }} leave</p>
                </div>
                <div class="isp-hr-payroll-kpi isp-hr-payroll-kpi--payroll">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/70">This month payroll</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-white">{{ number_format($stats['current_run_net'], 0) }} <span class="text-sm font-semibold">BDT</span></p>
                    <p class="mt-0.5 text-xs text-white/65">{{ ucfirst($stats['current_run_status'] ?? 'not generated') }}</p>
                </div>
                <div class="isp-hr-payroll-kpi isp-hr-payroll-kpi--gross">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Monthly gross (active)</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-white">{{ number_format($stats['monthly_gross'], 0) }} <span class="text-sm font-semibold">BDT</span></p>
                    <p class="mt-0.5 text-xs text-white/65">YTD paid {{ number_format($stats['ytd_paid'], 0) }} BDT</p>
                </div>
            </div>
        </div>

        <div class="isp-hub-stat-grid">
            <div class="isp-stat-card border-l-4 border-l-rose-500">
                <p class="text-xs font-bold uppercase tracking-wide text-rose-600 dark:text-rose-400">Unmarked attendance</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $stats['unmarked_today'] }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $stats['today_label'] }}</p>
            </div>
            <div class="isp-stat-card border-l-4 border-l-amber-500">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">Draft payroll runs</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $stats['draft_runs'] }}</p>
            </div>
            <div class="isp-stat-card border-l-4 border-l-fuchsia-500">
                <p class="text-xs font-bold uppercase tracking-wide text-fuchsia-600 dark:text-fuchsia-400">Last paid period</p>
                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ $stats['last_paid_label'] ?? '—' }}</p>
                @if ($stats['last_paid_net'] > 0)
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ number_format($stats['last_paid_net'], 0) }} BDT net</p>
                @endif
            </div>
            <div class="isp-stat-card border-l-4 border-l-violet-500">
                <p class="text-xs font-bold uppercase tracking-wide text-violet-600 dark:text-violet-400">Panel staff logins</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $stats['staff_users'] }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Collectors · NOC · admin</p>
            </div>
        </div>

        @if ($moduleGroups === [])
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                <p class="font-semibold">No HR modules visible</p>
                <p class="mt-1 opacity-90">Your account needs <span class="font-mono text-xs">payroll.view</span> or <span class="font-mono text-xs">staff.view</span>. Ask a super-admin to assign the <strong>Accountant</strong> or <strong>Admin</strong> role.</p>
            </div>
        @else
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
        @endif

        @if ($quickActions !== [])
            <section class="isp-accounting-quick" aria-label="Quick HR actions">
                <div class="isp-accounting-quick-header">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Quick actions</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Daily HR & payroll tasks</p>
                    </div>
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
        @endif

        <x-isp.hub-footer :links="$footerLinks" />
    </div>
</x-filament-panels::page>
