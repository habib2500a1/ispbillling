<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksHrAccess;
use App\Filament\Resources\AttendanceRecordResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\PayrollRunResource;
use App\Models\AttendanceRecord;
use App\Models\PayrollRun;
use App\Services\Hr\HrPayrollHubService;
use Filament\Pages\Page;

class HrReportsPage extends Page
{
    use ChecksHrAccess;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static string $view = 'filament.pages.hr-reports';

    protected static ?string $navigationLabel = 'HR Reports';

    protected static ?string $title = 'HR Reports';

    protected static ?string $slug = 'hr-reports';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return app(HrPayrollHubService::class)->snapshot();
    }

    /**
     * @return list<array{label: string, value: string, url: string}>
     */
    public function getReportLinks(): array
    {
        return [
            [
                'label' => 'Employee directory',
                'value' => (string) $this->getStats()['total_employees'].' staff',
                'url' => EmployeeResource::getUrl('index'),
            ],
            [
                'label' => 'Attendance log',
                'value' => $this->getStats()['attendance_marked_pct'].'% marked today',
                'url' => AttendanceRecordResource::getUrl('index'),
            ],
            [
                'label' => 'Payroll runs',
                'value' => $this->getStats()['period_label'],
                'url' => PayrollRunResource::getUrl('index'),
            ],
            [
                'label' => 'Leave today',
                'value' => (string) $this->getStats()['leave_today'].' on leave',
                'url' => HrLeaveManagementPage::getUrl(),
            ],
        ];
    }

    /**
     * @return list<array{month: string, status: string, net: float}>
     */
    public function getRecentPayrollRuns(): array
    {
        return PayrollRun::query()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(6)
            ->get()
            ->map(fn (PayrollRun $run): array => [
                'month' => $run->periodLabel(),
                'status' => (string) $run->status,
                'net' => round((float) $run->total_net, 2),
            ])
            ->all();
    }

    /**
     * @return array{present: int, absent: int, leave: int, holiday: int}
     */
    public function getMonthAttendanceBreakdown(): array
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $rows = AttendanceRecord::query()
            ->whereBetween('work_date', [$start, $end])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'present' => (int) ($rows['present'] ?? 0),
            'absent' => (int) ($rows['absent'] ?? 0),
            'leave' => (int) ($rows['leave'] ?? 0),
            'holiday' => (int) ($rows['holiday'] ?? 0),
        ];
    }
}
