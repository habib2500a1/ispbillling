<?php

namespace App\Support;

use App\Filament\Pages\HrPayrollHub;
use App\Filament\Resources\AttendanceOfficeLocationResource;
use App\Filament\Resources\AttendanceRecordResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\PayrollRunResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class HrmSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'hr_overview',
                'label' => 'HR overview',
                'icon' => 'heroicon-o-squares-2x2',
                'sort' => 1,
                'url' => HrPayrollHub::getUrl(),
                'active_routes' => ['filament.admin.pages.hr-payroll-hub'],
            ],
            [
                'key' => 'employees',
                'label' => 'Employees (All)',
                'icon' => 'heroicon-o-users',
                'sort' => 2,
                'url' => EmployeeResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.employees.index',
                    'filament.admin.resources.employees.edit',
                ],
            ],
            [
                'key' => 'add_employee',
                'label' => 'Add Employee',
                'icon' => 'heroicon-o-user-plus',
                'sort' => 3,
                'url' => EmployeeResource::getUrl('create'),
                'active_routes' => ['filament.admin.resources.employees.create'],
            ],
            [
                'key' => 'employee_salary',
                'label' => 'Employee Salary',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 4,
                'url' => PayrollRunResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.payroll-runs.index',
                    'filament.admin.resources.payroll-runs.view',
                ],
            ],
            [
                'key' => 'attendance',
                'label' => 'Attendance',
                'icon' => 'heroicon-o-clock',
                'sort' => 5,
                'url' => AttendanceRecordResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.attendance-records.index',
                    'filament.admin.resources.attendance-records.create',
                    'filament.admin.resources.attendance-records.edit',
                ],
            ],
            [
                'key' => 'office_locations',
                'label' => 'Office locations',
                'icon' => 'heroicon-o-map-pin',
                'sort' => 6,
                'url' => AttendanceOfficeLocationResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.attendance-office-locations.index',
                    'filament.admin.resources.attendance-office-locations.create',
                    'filament.admin.resources.attendance-office-locations.edit',
                ],
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
            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('HRM')
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
}
