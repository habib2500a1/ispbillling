<x-filament-panels::page class="isp-wf-page isp-hub-page">
    <link rel="stylesheet" href="{{ asset('css/workforce-hub.css') }}?v={{ @filemtime(public_path('css/workforce-hub.css')) ?: 1 }}">
    <script src="{{ asset('js/workforce-hub.js') }}?v={{ @filemtime(public_path('js/workforce-hub.js')) ?: 1 }}" defer data-cfasync="false"></script>

    <div class="space-y-5">
        <section class="isp-wf-hero isp-wf-glass">
            <p class="text-xs uppercase tracking-wider opacity-80 mb-1">ISP Workforce Operations</p>
            <h1 class="isp-wf-hero__title">Workforce Operations Center</h1>
            <p class="isp-wf-hero__sub">
                Employees · attendance · payroll · technicians · tasks · performance — {{ $workforce['period_label'] ?? now()->format('F Y') }}
            </p>
            <div class="isp-wf-search" wire:ignore.self>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search employee, ID, department, task, attendance…"
                    autocomplete="off"
                    aria-label="Global workforce search"
                >
                @if (strlen($searchQuery) >= 2)
                    <div class="isp-wf-search-results">
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
                <span class="isp-wf-pill">{{ $kpis['active_employees'] ?? 0 }} active</span>
                <span class="isp-wf-pill">{{ $workforce['attendance_marked_pct'] ?? 0 }}% marked today</span>
                <span class="isp-wf-pill isp-wf-pill--accent">{{ $workforce['performance']['attendance_rate'] ?? 0 }}% attendance rate</span>
            </div>
        </section>

        <div class="isp-wf-kpi-grid">
            @foreach ($kpiCards as $card)
                <div class="isp-wf-kpi isp-wf-glass {{ $card['class'] }}">
                    <span>{{ $card['label'] }}</span>
                    <strong data-wf-kpi="{{ (int) ($kpis[$card['key']] ?? 0) }}">{{ number_format($kpis[$card['key']] ?? 0) }}</strong>
                </div>
            @endforeach
        </div>

        <div class="isp-wf-filters isp-wf-glass p-3 flex flex-wrap gap-2 items-center">
            <span class="text-xs uppercase opacity-60">Filter</span>
            <select wire:model.live="filterDepartment" class="isp-wf-select text-sm">
                <option value="">All departments</option>
                @foreach ($workforce['hr_analytics']['department_breakdown'] ?? [] as $dept)
                    <option value="{{ $dept['department'] }}">{{ $dept['department'] }} ({{ $dept['count'] }})</option>
                @endforeach
            </select>
            @if ($filterDepartment)
                <button type="button" wire:click="$set('filterDepartment', '')" class="isp-wf-tab text-xs">Clear</button>
            @endif
        </div>

        <div class="isp-wf-tabs">
            @foreach (['dashboard' => 'Dashboard', 'workforce' => 'Workforce', 'attendance' => 'Attendance', 'payroll' => 'Payroll', 'technicians' => 'Technicians', 'tasks' => 'Tasks', 'analytics' => 'Analytics'] as $tab => $label)
                <button type="button" wire:click="setTab('{{ $tab }}')" class="isp-wf-tab {{ $activeTab === $tab ? 'isp-wf-tab--active' : '' }}">{{ $label }}</button>
            @endforeach
            <button type="button" wire:click="refreshWorkforce" class="isp-wf-tab ml-auto" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="refreshWorkforce">↻ Refresh</span>
                <span wire:loading wire:target="refreshWorkforce">…</span>
            </button>
        </div>

        <div class="isp-wf-layout">
            <nav class="isp-wf-layout__nav isp-wf-glass p-3">
                <p class="text-xs uppercase opacity-60 mb-2 px-1">HR modules</p>
                @foreach ($navLinks as $link)
                    @if (\App\Support\HrmSidebarRegistry::canSeeEntry($link['key']))
                        <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    @endif
                @endforeach
            </nav>

            <main class="space-y-4">
                @if (in_array($activeTab, ['dashboard', 'workforce', 'attendance', 'payroll', 'technicians', 'tasks'], true))
                    <section class="isp-wf-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Quick actions</h2>
                        <div class="isp-wf-quick-grid">
                            @foreach ($quickActions as $action)
                                <a href="{{ $action['url'] }}" class="isp-wf-quick isp-wf-glass isp-wf-quick--{{ $action['tone'] }}">
                                    <x-filament::icon :icon="'heroicon-o-'.$action['icon']" class="h-5 w-5" />
                                    <strong>{{ $action['label'] }}</strong>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($activeTab === 'dashboard')
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section class="isp-wf-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Payroll snapshot</h2>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="isp-wf-stat"><span>Month net</span><strong>{{ number_format($workforce['current_run_net'] ?? 0, 0) }} BDT</strong></div>
                                <div class="isp-wf-stat"><span>Monthly gross</span><strong>{{ number_format($workforce['monthly_gross'] ?? 0, 0) }} BDT</strong></div>
                                <div class="isp-wf-stat"><span>YTD paid</span><strong>{{ number_format($workforce['ytd_paid'] ?? 0, 0) }} BDT</strong></div>
                                <div class="isp-wf-stat"><span>Draft runs</span><strong>{{ $workforce['draft_runs'] ?? 0 }}</strong></div>
                            </div>
                        </section>
                        <section class="isp-wf-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Technician ops</h2>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="isp-wf-stat"><span>Open tickets</span><strong>{{ $workforce['technicians_ops']['open_tickets'] ?? 0 }}</strong></div>
                                <div class="isp-wf-stat"><span>Visits today</span><strong>{{ $workforce['technicians_ops']['visits_today'] ?? 0 }}</strong></div>
                                <div class="isp-wf-stat"><span>Avg resolve</span><strong>{{ $workforce['technicians_ops']['avg_resolution_hours'] ?? 0 }}h</strong></div>
                                <div class="isp-wf-stat"><span>Delayed tasks</span><strong>{{ $workforce['tasks']['delayed'] ?? 0 }}</strong></div>
                            </div>
                        </section>
                    </div>

                    <section class="isp-wf-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Employee timeline</h2>
                        <ul class="isp-wf-timeline">
                            @forelse ($workforce['recent_timeline'] ?? [] as $ev)
                                <li class="isp-wf-timeline__item isp-wf-timeline__item--{{ $ev['type'] }}">
                                    <span class="isp-wf-timeline__dot"></span>
                                    <div>
                                        <strong>{{ $ev['label'] }}</strong>
                                        <em>{{ $ev['at'] }}</em>
                                    </div>
                                </li>
                            @empty
                                <li class="opacity-60 text-sm">No recent workforce events</li>
                            @endforelse
                        </ul>
                    </section>
                @endif

                @if (in_array($activeTab, ['workforce', 'attendance', 'payroll', 'technicians', 'tasks'], true))
                    @if ($moduleGroups === [])
                        <div class="isp-wf-glass p-6 text-sm">
                            <p class="font-semibold">No HR modules visible</p>
                            <p class="mt-1 opacity-80">Your account needs payroll.view or staff.view permission.</p>
                        </div>
                    @else
                        <div class="isp-wf-modules space-y-6">
                            @foreach ($moduleGroups as $group)
                                @if ($activeTab === 'workforce' || (
                                    ($activeTab === 'attendance' && $group['tone'] === 'amber') ||
                                    ($activeTab === 'payroll' && in_array($group['tone'], ['fuchsia', 'cyan'], true)) ||
                                    ($activeTab === 'technicians' && $group['tone'] === 'teal') ||
                                    ($activeTab === 'tasks' && $group['tone'] === 'indigo')
                                ))
                                    <section class="isp-wf-section isp-wf-section--{{ $group['tone'] }}">
                                        <header class="isp-wf-section-head">
                                            <span class="isp-wf-section-icon isp-wf-section-icon--{{ $group['tone'] }}">
                                                <x-filament::icon :icon="'heroicon-o-'.$group['icon']" class="h-5 w-5" />
                                            </span>
                                            <div>
                                                <h3 class="isp-wf-section-title">{{ $group['title'] }}</h3>
                                                <p class="text-sm opacity-70">{{ $group['subtitle'] }}</p>
                                            </div>
                                        </header>
                                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach ($group['items'] as $item)
                                                <a href="{{ $item['url'] }}" class="isp-wf-card isp-wf-card--{{ $group['tone'] }} group">
                                                    <div class="flex items-start gap-3">
                                                        <span class="isp-wf-card-icon isp-wf-card-icon--{{ $group['tone'] }}">
                                                            <x-filament::icon :icon="'heroicon-o-'.$item['icon']" class="h-5 w-5" />
                                                        </span>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex items-start justify-between gap-2">
                                                                <p class="font-semibold">{{ $item['title'] }}</p>
                                                                @if ($item['badge'])
                                                                    <span class="isp-wf-badge">{{ $item['badge'] }}</span>
                                                                @endif
                                                            </div>
                                                            <p class="mt-1 text-sm opacity-70">{{ $item['description'] }}</p>
                                                        </div>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endif

                @if ($activeTab === 'attendance')
                    <section class="isp-wf-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Attendance analytics — 7 days</h2>
                        <div class="isp-wf-chart-bars">
                            @foreach ($workforce['attendance']['weekly'] ?? [] as $day)
                                @php $max = max(collect($workforce['attendance']['weekly'] ?? [])->max('present'), 1); @endphp
                                <div class="isp-wf-chart-bar" title="{{ $day['label'] }}: {{ $day['present'] }} present">
                                    <div class="isp-wf-chart-bar__fill" style="height: {{ round(($day['present'] / $max) * 100) }}%"></div>
                                    <span>{{ $day['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                            <div class="isp-wf-stat"><span>Present</span><strong>{{ $kpis['present_today'] ?? 0 }}</strong></div>
                            <div class="isp-wf-stat"><span>Absent</span><strong>{{ $kpis['absent_today'] ?? 0 }}</strong></div>
                            <div class="isp-wf-stat"><span>Late</span><strong>{{ $kpis['late_today'] ?? 0 }}</strong></div>
                            <div class="isp-wf-stat"><span>GPS verified</span><strong>{{ $workforce['attendance']['gps_today'] ?? 0 }}</strong></div>
                        </div>
                    </section>
                @endif

                @if ($activeTab === 'payroll')
                    <section class="isp-wf-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Payroll trend</h2>
                        <table class="isp-wf-table">
                            <thead><tr><th>Period</th><th>Net (BDT)</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($workforce['hr_analytics']['payroll_trend'] ?? [] as $row)
                                    <tr>
                                        <td>{{ $row['month'] }}</td>
                                        <td class="tabular-nums">{{ number_format($row['net'], 0) }}</td>
                                        <td>{{ ucfirst($row['status']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="opacity-60">No payroll runs yet</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </section>
                    <section class="isp-wf-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Pending leave approvals</h2>
                        <ul class="isp-wf-list">
                            @forelse ($workforce['pending_leave'] ?? [] as $leave)
                                <li><a href="{{ $leave['url'] }}"><span>{{ $leave['employee'] }} — {{ $leave['type'] }}</span><strong>{{ $leave['dates'] }}</strong></a></li>
                            @empty
                                <li class="opacity-60 text-sm">No pending leave requests</li>
                            @endforelse
                        </ul>
                    </section>
                @endif

                @if ($activeTab === 'technicians')
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section class="isp-wf-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Technician ranking (30d)</h2>
                            <ul class="isp-wf-list">
                                @forelse ($workforce['technicians_ops']['ranking'] ?? [] as $i => $tech)
                                    <li><span>#{{ $i + 1 }} {{ $tech['name'] }}</span><strong>{{ $tech['score'] }} closed</strong></li>
                                @empty
                                    <li class="opacity-60 text-sm">No ranking data yet</li>
                                @endforelse
                            </ul>
                        </section>
                        <section class="isp-wf-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">GIS integration</h2>
                            <div class="grid gap-2">
                                @foreach ($workforce['gis_links'] ?? [] as $link)
                                    <a href="{{ $link['url'] }}" class="isp-wf-report-card">
                                        <x-filament::icon :icon="'heroicon-o-'.($link['icon'] ?? 'map')" class="h-5 w-5" />
                                        <span>{{ $link['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    </div>
                    <section class="isp-wf-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Field visits & routes</h2>
                        <table class="isp-wf-table">
                            <thead><tr><th>Technician</th><th>Visit</th><th>Scheduled</th><th>GPS</th></tr></thead>
                            <tbody>
                                @forelse ($workforce['field_visits'] ?? [] as $visit)
                                    <tr>
                                        <td>{{ $visit['technician'] ?? '—' }}</td>
                                        <td><a href="{{ $visit['url'] }}" class="text-primary-600">{{ Str::limit($visit['subject'], 40) }}</a></td>
                                        <td>{{ $visit['scheduled'] }}</td>
                                        <td>@if ($visit['has_gps'])<a href="{{ $visit['map_url'] }}" class="text-xs">Map →</a>@else—@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="opacity-60">No field visits scheduled</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </section>
                @endif

                @if ($activeTab === 'tasks')
                    <section class="isp-wf-glass p-4">
                        <div class="flex justify-between mb-3">
                            <h2 class="text-sm font-semibold">Recent tasks</h2>
                            <a href="{{ \App\Filament\Pages\TaskKanbanBoard::getUrl() }}" class="text-xs text-primary-600">Kanban →</a>
                        </div>
                        <table class="isp-wf-table">
                            <thead><tr><th>Task</th><th>Assignee</th><th>Status</th><th>Due</th></tr></thead>
                            <tbody>
                                @forelse ($workforce['recent_tasks'] ?? [] as $task)
                                    <tr>
                                        <td><a href="{{ $task['url'] }}">{{ Str::limit($task['title'], 36) }}</a></td>
                                        <td>{{ $task['assignee'] ?? '—' }}</td>
                                        <td>{{ $task['status'] }}</td>
                                        <td>{{ $task['due'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="opacity-60">No tasks yet</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </section>
                @endif

                @if ($activeTab === 'analytics')
                    <div class="grid gap-4 lg:grid-cols-3 mb-4">
                        <div class="isp-wf-analytic-card isp-wf-glass">
                            <span>Attendance rate</span>
                            <strong>{{ $workforce['performance']['attendance_rate'] ?? 0 }}%</strong>
                        </div>
                        <div class="isp-wf-analytic-card isp-wf-glass">
                            <span>Task completion</span>
                            <strong>{{ $workforce['performance']['task_completion_rate'] ?? 0 }}%</strong>
                        </div>
                        <div class="isp-wf-analytic-card isp-wf-glass">
                            <span>Month present days</span>
                            <strong>{{ $workforce['attendance']['month_present'] ?? 0 }}</strong>
                        </div>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section class="isp-wf-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Employee growth</h2>
                            <table class="isp-wf-table">
                                <thead><tr><th>Month</th><th>New hires</th></tr></thead>
                                <tbody>
                                    @foreach ($workforce['hr_analytics']['employee_growth'] ?? [] as $row)
                                        <tr><td>{{ $row['month'] }}</td><td>{{ $row['count'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                        <section class="isp-wf-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Department breakdown</h2>
                            <table class="isp-wf-table">
                                <thead><tr><th>Department</th><th>Active</th></tr></thead>
                                <tbody>
                                    @forelse ($departments as $row)
                                        <tr><td>{{ $row['department'] }}</td><td>{{ $row['count'] }}</td></tr>
                                    @empty
                                        <tr><td colspan="2" class="opacity-60">No departments</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                    <section class="isp-wf-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Reports</h2>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($workforce['report_links'] ?? [] as $report)
                                <a href="{{ $report['url'] }}" class="isp-wf-report-card">
                                    <x-filament::icon :icon="'heroicon-o-'.($report['icon'] ?? 'document-chart-bar')" class="h-5 w-5" />
                                    <span>{{ $report['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </main>
        </div>

        <nav class="isp-wf-mobile-bar" aria-label="Mobile workforce shortcuts">
            <a href="{{ \App\Filament\Resources\AttendanceRecordResource::getUrl('index') }}">
                <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5" /><span>Attendance</span>
            </a>
            <a href="{{ \App\Filament\Pages\TaskKanbanBoard::getUrl() }}">
                <x-filament::icon icon="heroicon-o-view-columns" class="h-5 w-5" /><span>Tasks</span>
            </a>
            <a href="{{ \App\Filament\Pages\FieldTechnicianCenter::getUrl() }}">
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="h-5 w-5" /><span>Tickets</span>
            </a>
            <a href="{{ \App\Filament\Pages\FiberPlantMap::getUrl() }}">
                <x-filament::icon icon="heroicon-o-map" class="h-5 w-5" /><span>GIS</span>
            </a>
            <a href="{{ \App\Filament\Pages\HrReportsPage::getUrl() }}">
                <x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5" /><span>Reports</span>
            </a>
        </nav>

        <x-isp.hub-footer :links="$footerLinks" />
    </div>
</x-filament-panels::page>
