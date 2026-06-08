<?php

namespace App\Support;

use App\Filament\Pages\HrAdvanceSalaryPage;
use App\Filament\Pages\HrLeaveManagementPage;
use App\Filament\Pages\HrPayrollGenerationPage;
use App\Filament\Pages\HrPayrollHub;
use App\Filament\Pages\HrReportsPage;
use App\Filament\Pages\HrSalaryPoliciesPage;
use App\Filament\Resources\AttendanceRecordResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\PayrollRunResource;
use App\Support\Rbac\StaffCapability;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class HrmSidebarRegistry
{
    /** Must match Filament NavigationGroup label for accordion + sort order. */
    public const GROUP_LABEL = 'HR Management';

    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'hr_dashboard',
                'label' => 'Workforce operations',
                'icon' => 'heroicon-o-chart-bar',
                'sort' => 1,
                'url' => HrPayrollHub::getUrl(),
                'active_routes' => ['filament.admin.pages.hr-payroll-hub'],
            ],
            [
                'key' => 'employees',
                'label' => 'Employees',
                'icon' => 'heroicon-o-users',
                'sort' => 2,
                'url' => EmployeeResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.employees.index',
                    'filament.admin.resources.employees.create',
                    'filament.admin.resources.employees.edit',
                ],
            ],
            [
                'key' => 'attendance',
                'label' => 'Attendance',
                'icon' => 'heroicon-o-calendar-days',
                'sort' => 3,
                'url' => AttendanceRecordResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.attendance-records.index',
                    'filament.admin.resources.attendance-records.create',
                    'filament.admin.resources.attendance-records.edit',
                ],
            ],
            [
                'key' => 'leave',
                'label' => 'Leave Management',
                'icon' => 'heroicon-o-sun',
                'sort' => 4,
                'url' => HrLeaveManagementPage::getUrl(),
                'active_routes' => ['filament.admin.pages.hr-leave-management'],
            ],
            [
                'key' => 'advance',
                'label' => 'Advance Salary',
                'icon' => 'heroicon-o-hand-raised',
                'sort' => 5,
                'url' => HrAdvanceSalaryPage::getUrl(),
                'active_routes' => ['filament.admin.pages.hr-advance-salary'],
            ],
            [
                'key' => 'payroll',
                'label' => 'Payroll Generation',
                'icon' => 'heroicon-o-calculator',
                'sort' => 6,
                'url' => HrPayrollGenerationPage::getUrl(),
                'active_routes' => [
                    'filament.admin.pages.hr-payroll-generation',
                    'filament.admin.resources.payroll-runs.index',
                    'filament.admin.resources.payroll-runs.view',
                ],
            ],
            [
                'key' => 'salary_policies',
                'label' => 'Salary Policies',
                'icon' => 'heroicon-o-scale',
                'sort' => 7,
                'url' => HrSalaryPoliciesPage::getUrl(),
                'active_routes' => ['filament.admin.pages.hr-salary-policies'],
            ],
            [
                'key' => 'reports',
                'label' => 'HR Reports',
                'icon' => 'heroicon-o-chart-pie',
                'sort' => 8,
                'url' => HrReportsPage::getUrl(),
                'active_routes' => ['filament.admin.pages.hr-reports'],
            ],
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function navigationItems(): array
    {
        if (Filament::getCurrentPanel() === null) {
            return [];
        }

        $items = [];

        foreach (self::definitions() as $entry) {
            if (! self::canSeeEntry($entry['key'])) {
                continue;
            }

            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group(self::GROUP_LABEL)
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($entry): bool {
                    foreach ($entry['active_routes'] as $route) {
                        if (request()->routeIs($route)) {
                            return true;
                        }
                    }

                    return false;
                });
        }

        return $items;
    }

    public static function hasVisibleEntries(): bool
    {
        foreach (self::definitions() as $entry) {
            if (self::canSeeEntry($entry['key'])) {
                return true;
            }
        }

        return false;
    }

    public static function canSeeEntry(string $key): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        if (StaffCapability::for($user)->isTenantAdmin()) {
            return true;
        }

        $cap = StaffCapability::for($user);

        if (! $cap->canHrm() && ! HrPayrollHub::canAccess() && ! EmployeeResource::canViewAny()) {
            return false;
        }

        return match ($key) {
            'hr_dashboard', 'reports' => HrPayrollHub::canAccess() || $cap->canHrm(),
            'employees', 'salary_policies' => EmployeeResource::canViewAny() || $cap->canHrm(),
            'attendance', 'leave' => AttendanceRecordResource::canViewAny() || $cap->canHrm(),
            'advance' => EmployeeResource::canViewAny() || $cap->canHrm() || HrPayrollHub::canAccess(),
            'payroll' => PayrollRunResource::canViewAny() || $cap->canHrm(),
            default => false,
        };
    }
}
