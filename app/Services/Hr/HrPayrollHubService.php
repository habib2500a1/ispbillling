<?php

namespace App\Services\Hr;

use App\Models\AttendanceOfficeLocation;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\TenantResolver;

final class HrPayrollHubService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $today = now()->toDateString();
        $month = (int) now()->month;
        $year = (int) now()->year;

        $activeEmployees = Employee::query()->where('is_active', true)->count();
        $totalEmployees = Employee::query()->count();
        $monthlyGross = (float) Employee::query()->where('is_active', true)->sum('base_salary');

        $todayRecords = AttendanceRecord::query()
            ->whereDate('work_date', $today)
            ->get();

        $presentToday = $todayRecords->where('status', 'present')->count();
        $absentToday = $todayRecords->where('status', 'absent')->count();
        $leaveToday = $todayRecords->where('status', 'leave')->count();
        $markedToday = $todayRecords->count();
        $unmarkedToday = max(0, $activeEmployees - $markedToday);

        $currentRun = PayrollRun::query()
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->first();

        $lastPaid = PayrollRun::query()
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->first();

        $draftRuns = PayrollRun::query()->where('status', 'draft')->count();
        $ytdPaid = (float) PayrollRun::query()
            ->where('status', 'paid')
            ->where('period_year', $year)
            ->sum('total_net');

        $staffUsers = User::query()->count();
        $officeLocations = AttendanceOfficeLocation::query()->where('is_active', true)->count();

        return [
            'period_label' => now()->format('F Y'),
            'today_label' => now()->format('d M Y'),
            'active_employees' => $activeEmployees,
            'total_employees' => $totalEmployees,
            'monthly_gross' => round($monthlyGross, 2),
            'present_today' => $presentToday,
            'absent_today' => $absentToday,
            'leave_today' => $leaveToday,
            'unmarked_today' => $unmarkedToday,
            'attendance_marked_pct' => $activeEmployees > 0
                ? (int) round(($markedToday / $activeEmployees) * 100)
                : 0,
            'current_run' => $currentRun,
            'current_run_status' => $currentRun?->status,
            'current_run_net' => round((float) ($currentRun?->total_net ?? 0), 2),
            'last_paid_label' => $lastPaid?->periodLabel(),
            'last_paid_net' => round((float) ($lastPaid?->total_net ?? 0), 2),
            'draft_runs' => $draftRuns,
            'ytd_paid' => round($ytdPaid, 2),
            'staff_users' => $staffUsers,
            'office_locations' => $officeLocations,
        ];
    }
}
