<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AttendanceRecordResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\PayrollRunResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Services\Accounting\PayrollService;
use App\Services\Hr\HrPayrollHubService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class HrPayrollHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static string $view = 'filament.pages.hr-payroll-hub';

    protected static ?string $navigationLabel = 'HR & payroll';

    protected static ?string $title = 'HR & payroll';

    protected static ?string $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return app(HrPayrollHubService::class)->snapshot();
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
            $actions[] = [
                'label' => 'Add employee',
                'url' => EmployeeResource::getUrl('create'),
                'icon' => 'user-plus',
                'tone' => 'rose',
            ];
            $actions[] = [
                'label' => 'Attendance',
                'url' => AttendanceRecordResource::getUrl('create'),
                'icon' => 'clock',
                'tone' => 'amber',
            ];
        }

        if ($access['payroll_manage']) {
            $actions[] = [
                'label' => 'Payroll',
                'url' => PayrollRunResource::getUrl('index'),
                'icon' => 'currency-dollar',
                'tone' => 'fuchsia',
            ];
        }

        if ($access['staff_manage']) {
            $actions[] = [
                'label' => 'Staff login',
                'url' => UserResource::getUrl('create'),
                'icon' => 'key',
                'tone' => 'violet',
            ];
        }

        if ($access['staff_view']) {
            $actions[] = [
                'label' => 'All users',
                'url' => UserResource::getUrl('index'),
                'icon' => 'users',
                'tone' => 'cyan',
            ];
        }

        if ($access['security']) {
            $actions[] = [
                'label' => 'Security',
                'url' => ManageStaffSecurity::getUrl(),
                'icon' => 'shield-check',
                'tone' => 'indigo',
            ];
        }

        return $actions;
    }

    /**
     * @return list<array{title: string, subtitle: string, tone: string, icon: string, items: list<array{title: string, description: string, url: string, badge: ?string, icon: string}>}>
     */
    public function getModuleGroups(): array
    {
        $stats = $this->getStats();
        $access = $this->getAccess();
        $groups = [];

        if ($access['payroll_view']) {
            $runBadge = $stats['current_run']
                ? ucfirst((string) $stats['current_run_status'])
                : 'Not generated';

            $groups[] = [
                'title' => 'Staff directory',
                'subtitle' => 'Employees, designations & base salary',
                'tone' => 'rose',
                'icon' => 'user-group',
                'items' => [
                    [
                        'title' => 'Employees',
                        'description' => $stats['active_employees'].' active · '.$stats['total_employees'].' total',
                        'url' => EmployeeResource::getUrl('index'),
                        'badge' => (string) $stats['active_employees'],
                        'icon' => 'users',
                    ],
                    ...($access['payroll_manage'] ? [[
                        'title' => 'Add employee',
                        'description' => 'New staff profile & salary',
                        'url' => EmployeeResource::getUrl('create'),
                        'badge' => null,
                        'icon' => 'user-plus',
                    ]] : []),
                ],
            ];

            $groups[] = [
                'title' => 'Attendance',
                'subtitle' => 'Daily check-in · leave · holidays',
                'tone' => 'amber',
                'icon' => 'clock',
                'items' => [
                    [
                        'title' => 'Attendance log',
                        'description' => $stats['present_today'].' present · '.$stats['absent_today'].' absent today',
                        'url' => AttendanceRecordResource::getUrl('index'),
                        'badge' => $stats['attendance_marked_pct'].'%',
                        'icon' => 'calendar-days',
                    ],
                    ...($access['payroll_manage'] ? [[
                        'title' => 'Mark attendance',
                        'description' => $stats['unmarked_today'].' staff not marked for '.$stats['today_label'],
                        'url' => AttendanceRecordResource::getUrl('create'),
                        'badge' => $stats['unmarked_today'] > 0 ? (string) $stats['unmarked_today'] : null,
                        'icon' => 'plus-circle',
                    ]] : []),
                ],
            ];

            $groups[] = [
                'title' => 'Payroll',
                'subtitle' => 'Monthly salary runs & ledger posting',
                'tone' => 'fuchsia',
                'icon' => 'currency-dollar',
                'items' => [
                    [
                        'title' => 'Payroll runs',
                        'description' => $stats['period_label'].' — '.number_format($stats['current_run_net'], 0).' BDT net',
                        'url' => PayrollRunResource::getUrl('index'),
                        'badge' => $runBadge,
                        'icon' => 'banknotes',
                    ],
                    [
                        'title' => 'Accounting hub',
                        'description' => 'Payroll posts to GL · chart '.$stats['ytd_paid'].' BDT paid YTD',
                        'url' => AccountingHub::getUrl(),
                        'badge' => null,
                        'icon' => 'calculator',
                    ],
                ],
            ];
        }

        if ($access['staff_view'] || $access['staff_manage']) {
            $items = [
                [
                    'title' => 'Staff users',
                    'description' => $stats['staff_users'].' panel logins (collectors, NOC, admin)',
                    'url' => UserResource::getUrl('index'),
                    'badge' => (string) $stats['staff_users'],
                    'icon' => 'user-circle',
                ],
            ];

            if ($access['staff_manage']) {
                $items[] = [
                    'title' => 'Create staff login',
                    'description' => 'Username, role & branch access',
                    'url' => UserResource::getUrl('create'),
                    'badge' => null,
                    'icon' => 'key',
                ];
                $items[] = [
                    'title' => 'Roles & permissions',
                    'description' => 'RBAC templates — billing, network, payroll',
                    'url' => RoleResource::getUrl('index'),
                    'badge' => null,
                    'icon' => 'shield-check',
                ];
            }

            if ($access['security']) {
                $items[] = [
                    'title' => 'Staff security',
                    'description' => '2FA policy · IP allowlist',
                    'url' => ManageStaffSecurity::getUrl(),
                    'badge' => null,
                    'icon' => 'lock-closed',
                ];
            }

            $groups[] = [
                'title' => 'Access & security',
                'subtitle' => 'Logins, roles, 2FA & IP rules',
                'tone' => 'violet',
                'icon' => 'finger-print',
                'items' => $items,
            ];
        }

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
