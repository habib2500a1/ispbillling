<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AttendanceOfficeLocationResource;
use App\Filament\Resources\AttendanceRecordResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\PayrollRunResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Services\Accounting\PayrollService;
use App\Services\Hr\WorkforceHubDashboardService;
use App\Support\HrmSidebarRegistry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class HrPayrollHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static string $view = 'filament.pages.hr-payroll-hub';

    protected static ?string $navigationLabel = 'Workforce operations';

    protected static ?string $title = 'Workforce Operations Center';

    protected static ?string $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $workforce = [];

    public string $searchQuery = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $activeTab = 'dashboard';

    public string $filterDepartment = '';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->refreshWorkforce();
    }

    public function refreshWorkforce(): void
    {
        $this->workforce = app(WorkforceHubDashboardService::class)->snapshot();
    }

    public function updatedSearchQuery(): void
    {
        $this->searchResults = app(WorkforceHubDashboardService::class)->search($this->searchQuery);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $w = $this->workforce;
        $kpis = $w['kpis'] ?? [];
        $access = $this->getAccess();

        $kpiCards = [
            ['key' => 'total_employees', 'label' => 'Total employees', 'class' => 'isp-wf-kpi--total'],
            ['key' => 'active_employees', 'label' => 'Active', 'class' => 'isp-wf-kpi--active'],
            ['key' => 'technicians', 'label' => 'Technicians', 'class' => 'isp-wf-kpi--tech'],
            ['key' => 'support_staff', 'label' => 'Support staff', 'class' => 'isp-wf-kpi--support'],
            ['key' => 'administrators', 'label' => 'Administrators', 'class' => 'isp-wf-kpi--admin'],
            ['key' => 'present_today', 'label' => 'Present today', 'class' => 'isp-wf-kpi--present'],
            ['key' => 'absent_today', 'label' => 'Absent today', 'class' => 'isp-wf-kpi--absent'],
            ['key' => 'leave_today', 'label' => 'Leave today', 'class' => 'isp-wf-kpi--leave'],
            ['key' => 'open_tasks', 'label' => 'Open tasks', 'class' => 'isp-wf-kpi--tasks'],
            ['key' => 'completed_tasks', 'label' => 'Tasks done (MTD)', 'class' => 'isp-wf-kpi--done'],
        ];

        $departments = collect($w['hr_analytics']['department_breakdown'] ?? [])
            ->when($this->filterDepartment !== '', fn ($c) => $c->where('department', $this->filterDepartment))
            ->values()
            ->all();

        return [
            'workforce' => $w,
            'kpis' => $kpis,
            'access' => $access,
            'kpiCards' => $kpiCards,
            'quickActions' => $this->getQuickActions(),
            'moduleGroups' => $this->getModuleGroups(),
            'navLinks' => HrmSidebarRegistry::definitions(),
            'departments' => $departments,
            'footerLinks' => [
                ['url' => StaffControlHub::getUrl(), 'label' => 'Staff security', 'icon' => 'heroicon-o-shield-check'],
                ['url' => AccountingHub::getUrl(), 'label' => 'Finance', 'icon' => 'heroicon-o-calculator'],
                ['url' => SupportHub::getUrl(), 'label' => 'Support', 'icon' => 'heroicon-o-lifebuoy'],
                ['url' => FiberPlantMap::getUrl(), 'label' => 'GIS map', 'icon' => 'heroicon-o-map'],
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getAccess(): array
    {
        $user = auth()->user();

        return [
            'payroll_view' => static::userCanPayrollView($user),
            'payroll_manage' => static::userCanPayrollManage($user),
            'staff_view' => static::userCanStaffView($user),
            'staff_manage' => static::userCanStaffManage($user),
            'security' => static::userCanSecurity($user),
        ];
    }

    /**
     * @return list<array{label: string, url: string, icon: string, tone: string}>
     */
    public function getQuickActions(): array
    {
        $access = $this->getAccess();
        $actions = [];

        if ($access['payroll_manage']) {
            $actions[] = ['label' => 'Add employee', 'url' => EmployeeResource::getUrl('create'), 'icon' => 'user-plus', 'tone' => 'rose'];
            $actions[] = ['label' => 'Mark attendance', 'url' => AttendanceRecordResource::getUrl('create'), 'icon' => 'clock', 'tone' => 'amber'];
            $actions[] = ['label' => 'Leave request', 'url' => HrLeaveManagementPage::getUrl(), 'icon' => 'sun', 'tone' => 'cyan'];
            $actions[] = ['label' => 'Advance salary', 'url' => HrAdvanceSalaryPage::getUrl(), 'icon' => 'banknotes', 'tone' => 'emerald'];
            $actions[] = ['label' => 'Payroll runs', 'url' => PayrollRunResource::getUrl('index'), 'icon' => 'currency-dollar', 'tone' => 'fuchsia'];
        }

        if ($access['staff_manage']) {
            $actions[] = ['label' => 'Staff login', 'url' => UserResource::getUrl('create'), 'icon' => 'key', 'tone' => 'violet'];
        }

        $actions[] = ['label' => 'Task board', 'url' => TaskKanbanBoard::getUrl(), 'icon' => 'view-columns', 'tone' => 'indigo'];
        $actions[] = ['label' => 'Field techs', 'url' => FieldTechnicianCenter::getUrl(), 'icon' => 'wrench-screwdriver', 'tone' => 'teal'];
        $actions[] = ['label' => 'GIS map', 'url' => FiberPlantMap::getUrl(), 'icon' => 'map', 'tone' => 'slate'];

        if ($access['security']) {
            $actions[] = ['label' => 'Security', 'url' => ManageStaffSecurity::getUrl(), 'icon' => 'shield-check', 'tone' => 'orange'];
        }

        return $actions;
    }

    /**
     * @return list<array{title: string, subtitle: string, tone: string, icon: string, items: list<array{title: string, description: string, url: string, badge: ?string, icon: string}>}>
     */
    public function getModuleGroups(): array
    {
        $stats = $this->workforce ?: app(WorkforceHubDashboardService::class)->snapshot();
        $access = $this->getAccess();
        $groups = [];

        if ($access['payroll_view']) {
            $runBadge = $stats['current_run']
                ? ucfirst((string) $stats['current_run_status'])
                : 'Not generated';

            $groups[] = [
                'title' => 'Employee management',
                'subtitle' => 'Profiles · ID · department · salary',
                'tone' => 'rose',
                'icon' => 'user-group',
                'items' => [
                    ['title' => 'Employees', 'description' => ($stats['active_employees'] ?? 0).' active · '.($stats['total_employees'] ?? 0).' total', 'url' => EmployeeResource::getUrl('index'), 'badge' => (string) ($stats['active_employees'] ?? 0), 'icon' => 'users'],
                    ...($access['payroll_manage'] ? [['title' => 'Add employee', 'description' => 'New profile & salary structure', 'url' => EmployeeResource::getUrl('create'), 'badge' => null, 'icon' => 'user-plus']] : []),
                    ['title' => 'My salary', 'description' => 'Personal payslip view', 'url' => AccountsMySalaryPage::getUrl(), 'badge' => null, 'icon' => 'banknotes'],
                ],
            ];

            $groups[] = [
                'title' => 'Attendance center',
                'subtitle' => 'GPS · mobile · office · shift',
                'tone' => 'amber',
                'icon' => 'clock',
                'items' => [
                    ['title' => 'Attendance log', 'description' => ($stats['present_today'] ?? 0).' present · '.($stats['kpis']['late_today'] ?? 0).' late today', 'url' => AttendanceRecordResource::getUrl('index'), 'badge' => ($stats['attendance_marked_pct'] ?? 0).'%', 'icon' => 'calendar-days'],
                    ...($access['payroll_manage'] ? [['title' => 'Mark attendance', 'description' => ($stats['unmarked_today'] ?? 0).' unmarked · '.$stats['today_label'], 'url' => AttendanceRecordResource::getUrl('create'), 'badge' => ($stats['unmarked_today'] ?? 0) > 0 ? (string) $stats['unmarked_today'] : null, 'icon' => 'plus-circle']] : []),
                    ...($access['payroll_manage'] ? [['title' => 'Office GPS zones', 'description' => ($stats['office_locations'] ?? 0).' geofence location(s)', 'url' => AttendanceOfficeLocationResource::getUrl(), 'badge' => (string) ($stats['office_locations'] ?? 0), 'icon' => 'map-pin']] : []),
                ],
            ];

            $groups[] = [
                'title' => 'Leave management',
                'subtitle' => 'Casual · sick · annual · emergency',
                'tone' => 'cyan',
                'icon' => 'sun',
                'items' => [
                    ['title' => 'Leave requests', 'description' => ($stats['leave']['pending'] ?? 0).' pending approval', 'url' => HrLeaveManagementPage::getUrl(), 'badge' => ($stats['leave']['pending'] ?? 0) > 0 ? (string) $stats['leave']['pending'] : null, 'icon' => 'clipboard-document-list'],
                    ['title' => 'Leave today', 'description' => ($stats['leave_today'] ?? 0).' on leave', 'url' => HrLeaveManagementPage::getUrl(), 'badge' => null, 'icon' => 'sun'],
                ],
            ];

            $groups[] = [
                'title' => 'Payroll center',
                'subtitle' => 'Salary · overtime · deductions · bonuses',
                'tone' => 'fuchsia',
                'icon' => 'currency-dollar',
                'items' => [
                    ...($access['payroll_manage'] ? [['title' => 'Advance salary', 'description' => 'Requests & deductions', 'url' => HrAdvanceSalaryPage::getUrl(), 'badge' => null, 'icon' => 'hand-raised']] : []),
                    ['title' => 'Payroll runs', 'description' => ($stats['period_label'] ?? '').' — '.number_format($stats['current_run_net'] ?? 0, 0).' BDT net', 'url' => PayrollRunResource::getUrl('index'), 'badge' => $runBadge, 'icon' => 'banknotes'],
                    ['title' => 'Generate payroll', 'description' => 'Monthly salary sheet', 'url' => HrPayrollGenerationPage::getUrl(), 'badge' => null, 'icon' => 'calculator'],
                    ['title' => 'Salary policies', 'description' => 'Late fine · PF · holidays', 'url' => HrSalaryPoliciesPage::getUrl(), 'badge' => null, 'icon' => 'scale'],
                    ['title' => 'Finance hub', 'description' => 'YTD paid '.number_format($stats['ytd_paid'] ?? 0, 0).' BDT', 'url' => AccountingHub::getUrl(), 'badge' => null, 'icon' => 'calculator'],
                ],
            ];
        }

        $groups[] = [
            'title' => 'Technician operations',
            'subtitle' => 'Tickets · visits · resolution · GIS',
            'tone' => 'teal',
            'icon' => 'wrench-screwdriver',
            'items' => [
                ['title' => 'Field technician center', 'description' => ($stats['technicians_ops']['pending_visits'] ?? 0).' pending visits', 'url' => FieldTechnicianCenter::getUrl(), 'badge' => (string) ($stats['technicians_ops']['visits_today'] ?? 0).' today', 'icon' => 'map-pin'],
                ['title' => 'Support tickets', 'description' => ($stats['technicians_ops']['open_tickets'] ?? 0).' open · avg '.$stats['technicians_ops']['avg_resolution_hours'].'h resolve', 'url' => SupportHub::getUrl(), 'badge' => null, 'icon' => 'lifebuoy'],
                ['title' => 'GIS intelligence map', 'description' => 'Technician routes & field activity', 'url' => FiberPlantMap::getUrl(), 'badge' => 'GIS', 'icon' => 'map'],
            ],
        ];

        $groups[] = [
            'title' => 'Task management',
            'subtitle' => 'Daily · assigned · delayed tasks',
            'tone' => 'indigo',
            'icon' => 'view-columns',
            'items' => [
                ['title' => 'Kanban task board', 'description' => ($stats['tasks']['open'] ?? 0).' open · '.($stats['tasks']['delayed'] ?? 0).' delayed', 'url' => TaskKanbanBoard::getUrl(), 'badge' => (string) ($stats['tasks']['open'] ?? 0), 'icon' => 'view-columns'],
                ['title' => 'All tasks', 'description' => 'Internal task list', 'url' => \App\Filament\Resources\InternalTaskResource::getUrl('index'), 'badge' => null, 'icon' => 'clipboard-document-check'],
            ],
        ];

        if ($access['staff_view'] || $access['staff_manage']) {
            $items = [
                ['title' => 'Staff users', 'description' => ($stats['staff_users'] ?? 0).' panel logins', 'url' => UserResource::getUrl('index'), 'badge' => (string) ($stats['staff_users'] ?? 0), 'icon' => 'user-circle'],
            ];
            if ($access['staff_manage']) {
                $items[] = ['title' => 'Create staff login', 'description' => 'Username, role & branch', 'url' => UserResource::getUrl('create'), 'badge' => null, 'icon' => 'key'];
                $items[] = ['title' => 'Roles & permissions', 'description' => 'RBAC templates', 'url' => RoleResource::getUrl('index'), 'badge' => null, 'icon' => 'shield-check'];
            }
            if ($access['security']) {
                $items[] = ['title' => 'Staff security', 'description' => '2FA · IP allowlist', 'url' => ManageStaffSecurity::getUrl(), 'badge' => null, 'icon' => 'lock-closed'];
            }
            $groups[] = [
                'title' => 'Access & security',
                'subtitle' => 'Logins, roles, 2FA',
                'tone' => 'violet',
                'icon' => 'finger-print',
                'items' => $items,
            ];
        }

        $groups[] = [
            'title' => 'Performance & reports',
            'subtitle' => 'Analytics · rankings · HR reports',
            'tone' => 'orange',
            'icon' => 'chart-bar',
            'items' => [
                ['title' => 'HR reports', 'description' => 'Attendance · payroll summaries', 'url' => HrReportsPage::getUrl(), 'badge' => 'Open', 'icon' => 'chart-pie'],
                ['title' => 'Staff control hub', 'description' => 'Admin & permission matrix', 'url' => StaffControlHub::getUrl(), 'badge' => null, 'icon' => 'users'],
                ['title' => 'Mobile apps hub', 'description' => 'Field staff mobile API', 'url' => MobileAppsHub::getUrl(), 'badge' => null, 'icon' => 'device-phone-mobile'],
            ],
        ];

        return $groups;
    }

    protected function getHeaderActions(): array
    {
        if (! static::userCanPayrollManage(auth()->user())) {
            return [];
        }

        return [
            Action::make('generatePayroll')
                ->label('Generate '.now()->format('F').' payroll')
                ->icon('heroicon-o-sparkles')
                ->action(function (): void {
                    $run = app(PayrollService::class)->generateDraft(
                        (int) now()->month,
                        (int) now()->year,
                    );
                    Notification::make()
                        ->title('Payroll draft ready')
                        ->body($run->periodLabel().' — net '.number_format((float) $run->total_net, 2).' BDT')
                        ->success()
                        ->send();
                    $this->refreshWorkforce();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return static::userCanPayrollView(auth()->user())
            || static::userCanStaffView(auth()->user());
    }

    protected static function userCanPayrollView(?\App\Models\User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'isp-admin', 'admin'])) {
            return true;
        }

        return $user->can('payroll.view')
            || $user->can('payroll.manage')
            || $user->can('accounting.payroll')
            || $user->can('accounting.view');
    }

    protected static function userCanPayrollManage(?\App\Models\User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'isp-admin', 'admin'])) {
            return true;
        }

        return $user->can('payroll.manage') || $user->can('accounting.payroll');
    }

    protected static function userCanStaffView(?\App\Models\User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'isp-admin', 'admin'])) {
            return true;
        }

        return $user->can('staff.view');
    }

    protected static function userCanStaffManage(?\App\Models\User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'isp-admin', 'admin'])) {
            return true;
        }

        return $user->can('staff.manage');
    }

    protected static function userCanSecurity(?\App\Models\User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasRole(['super-admin', 'isp-admin'])
            || $user->can('security.manage');
    }
}
