<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\FieldVisit;
use App\Models\InternalTask;
use App\Models\PayrollRun;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\InternalTaskStatus;
use App\Support\PerformanceSettings;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only workforce command center aggregator (no HR/payroll logic changes).
 */
final class WorkforceHubDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::currentTenantId() ?? 1;
        $cacheKey = 'workforce_hub:snapshot:'.$tenantId;

        return SafeCache::remember($cacheKey, PerformanceSettings::hubCacheSeconds(), fn () => $this->build($tenantId));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId): array
    {
        $hr = app(HrPayrollHubService::class)->snapshot($tenantId);
        $today = now()->toDateString();

        $roleCounts = $this->roleCounts($tenantId);
        $tasks = $this->taskStats($tenantId);
        $attendance = $this->attendanceAnalytics($tenantId);
        $technicians = $this->technicianOps($tenantId);
        $leave = $this->leaveStats($tenantId);
        $performance = $this->performanceSnapshot($tenantId, $tasks);

        return array_merge($hr, [
            'kpis' => [
                'total_employees' => (int) ($hr['total_employees'] ?? 0),
                'active_employees' => (int) ($hr['active_employees'] ?? 0),
                'technicians' => $roleCounts['technicians'],
                'support_staff' => $roleCounts['support_staff'],
                'administrators' => $roleCounts['administrators'],
                'present_today' => (int) ($hr['present_today'] ?? 0),
                'absent_today' => (int) ($hr['absent_today'] ?? 0),
                'leave_today' => (int) ($hr['leave_today'] ?? 0),
                'late_today' => $attendance['late_today'],
                'open_tasks' => $tasks['open'],
                'completed_tasks' => $tasks['completed_month'],
                'delayed_tasks' => $tasks['delayed'],
            ],
            'role_counts' => $roleCounts,
            'tasks' => $tasks,
            'attendance' => $attendance,
            'technicians_ops' => $technicians,
            'leave' => $leave,
            'performance' => $performance,
            'hr_analytics' => [
                'employee_growth' => $this->employeeGrowth($tenantId),
                'attendance_trend' => $attendance['weekly'],
                'payroll_trend' => $this->payrollTrend($tenantId),
                'department_breakdown' => $this->departmentBreakdown($tenantId),
            ],
            'recent_timeline' => $this->recentTimeline($tenantId, 12),
            'pending_leave' => $this->pendingLeaveRequests($tenantId, 6),
            'recent_tasks' => $this->recentTasks($tenantId, 8),
            'field_visits' => $this->recentFieldVisits($tenantId, 8),
            'report_links' => $this->reportLinks(),
            'gis_links' => $this->gisLinks(),
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 25): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $tenantId = TenantResolver::currentTenantId();
        $likeOp = Employee::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $results = [];

        Employee::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where(function ($qb) use ($q, $likeOp): void {
                $qb->where('name', $likeOp, "%{$q}%")
                    ->orWhere('employee_code', 'like', "%{$q}%")
                    ->orWhere('department', $likeOp, "%{$q}%")
                    ->orWhere('designation', $likeOp, "%{$q}%");
            })
            ->limit(8)
            ->get(['id', 'name', 'employee_code', 'department', 'designation'])
            ->each(function (Employee $e) use (&$results): void {
                $results[] = [
                    'type' => 'employee',
                    'label' => $e->name,
                    'meta' => ($e->employee_code ?: '—').' · '.($e->department ?: '—'),
                    'url' => \App\Filament\Resources\EmployeeResource::getUrl('edit', ['record' => $e->id]),
                ];
            });

        InternalTask::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where('title', $likeOp, "%{$q}%")
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'title', 'status', 'priority'])
            ->each(function (InternalTask $t) use (&$results): void {
                $results[] = [
                    'type' => 'task',
                    'label' => $t->title,
                    'meta' => $t->status.' · '.$t->priority,
                    'url' => \App\Filament\Resources\InternalTaskResource::getUrl('edit', ['record' => $t->id]),
                ];
            });

        User::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where(function ($qb) use ($q, $likeOp): void {
                $qb->where('name', $likeOp, "%{$q}%")
                    ->orWhere('email', $likeOp, "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->each(function (User $u) use (&$results): void {
                $results[] = [
                    'type' => 'staff',
                    'label' => $u->name,
                    'meta' => $u->email ?? 'Panel login',
                    'url' => \App\Filament\Resources\UserResource::getUrl('edit', ['record' => $u->id]),
                ];
            });

        AttendanceRecord::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->whereHas('employee', function ($qb) use ($q, $likeOp): void {
                $qb->where('name', $likeOp, "%{$q}%");
            })
            ->orderByDesc('work_date')
            ->limit(4)
            ->with('employee:id,name')
            ->get(['id', 'employee_id', 'work_date', 'status'])
            ->each(function (AttendanceRecord $r) use (&$results): void {
                $results[] = [
                    'type' => 'attendance',
                    'label' => ($r->employee?->name ?? 'Staff').' · '.$r->work_date?->format('M j'),
                    'meta' => ucfirst((string) $r->status),
                    'url' => \App\Filament\Resources\AttendanceRecordResource::getUrl('edit', ['record' => $r->id]),
                ];
            });

        return array_slice($results, 0, $limit);
    }

    /**
     * @return array{technicians: int, support_staff: int, administrators: int}
     */
    private function roleCounts(int $tenantId): array
    {
        $base = Employee::query()->where('tenant_id', $tenantId)->where('is_active', true);

        $technicians = (clone $base)->where(function ($q): void {
            $q->whereIn('department', ['Field', 'NOC'])
                ->orWhere('designation', 'like', '%technician%')
                ->orWhere('designation', 'like', '%engineer%');
        })->count();

        return [
            'technicians' => $technicians,
            'support_staff' => (clone $base)->where('department', 'Support')->count(),
            'administrators' => (clone $base)->where('department', 'Admin')->count(),
        ];
    }

    /**
     * @return array{open: int, completed_month: int, delayed: int, in_progress: int}
     */
    private function taskStats(int $tenantId): array
    {
        $open = InternalTask::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [InternalTaskStatus::DONE, InternalTaskStatus::CANCELLED])
            ->count();

        return [
            'open' => $open,
            'in_progress' => InternalTask::query()
                ->where('tenant_id', $tenantId)
                ->where('status', InternalTaskStatus::IN_PROGRESS)
                ->count(),
            'completed_month' => InternalTask::query()
                ->where('tenant_id', $tenantId)
                ->where('status', InternalTaskStatus::DONE)
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->count(),
            'delayed' => InternalTask::query()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', [InternalTaskStatus::DONE, InternalTaskStatus::CANCELLED])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceAnalytics(int $tenantId): array
    {
        $today = now()->toDateString();
        $lateThreshold = $this->lateThresholdTime();

        $todayRecords = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('work_date', $today)
            ->where('status', 'present')
            ->whereNotNull('check_in')
            ->get(['check_in']);

        $lateToday = $todayRecords->filter(function (AttendanceRecord $r) use ($lateThreshold): bool {
            $checkIn = $r->check_in;
            if ($checkIn === null) {
                return false;
            }
            try {
                return Carbon::parse($today.' '.$checkIn)->gt($lateThreshold);
            } catch (\Throwable) {
                return false;
            }
        })->count();

        $weekly = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $weekly[] = [
                'label' => now()->subDays($i)->format('D'),
                'date' => $date,
                'present' => AttendanceRecord::query()
                    ->where('tenant_id', $tenantId)
                    ->whereDate('work_date', $date)
                    ->where('status', 'present')
                    ->count(),
                'absent' => AttendanceRecord::query()
                    ->where('tenant_id', $tenantId)
                    ->whereDate('work_date', $date)
                    ->where('status', 'absent')
                    ->count(),
            ];
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthPresent = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->where('status', 'present')
            ->count();

        return [
            'late_today' => $lateToday,
            'weekly' => $weekly,
            'month_present' => $monthPresent,
            'gps_today' => AttendanceRecord::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('work_date', $today)
                ->where('status', 'present')
                ->where('location_verified', true)
                ->count(),
        ];
    }

    private function lateThresholdTime(): Carbon
    {
        $start = (string) config('hr.policy.office_start_time', '09:00');
        $grace = (int) config('hr.policy.late_grace_minutes', 10);

        return Carbon::parse(now()->toDateString().' '.$start)->addMinutes($grace);
    }

    /**
     * @return array<string, mixed>
     */
    private function technicianOps(int $tenantId): array
    {
        $openTickets = SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress', 'waiting'])
            ->count();

        $completedMonth = SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['resolved', 'closed'])
            ->where(function ($q): void {
                $q->whereMonth('resolved_at', now()->month)
                    ->orWhereMonth('closed_at', now()->month);
            })
            ->count();

        $avgResolutionHours = SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->get(['created_at', 'resolved_at'])
            ->avg(fn (SupportTicket $t) => $t->created_at && $t->resolved_at
                ? $t->created_at->diffInHours($t->resolved_at)
                : null);

        $ranking = User::query()
            ->where('tenant_id', $tenantId)
            ->withCount([
                'assignedSupportTickets as closed_tickets' => fn ($q) => $q
                    ->whereIn('status', ['resolved', 'closed'])
                    ->where('updated_at', '>=', now()->subDays(30)),
            ])
            ->orderByDesc('closed_tickets')
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn (User $u) => [
                'name' => $u->name,
                'score' => (int) ($u->closed_tickets ?? 0),
            ])
            ->all();

        if ($ranking === []) {
            $ranking = FieldVisit::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays(30))
                ->select('assigned_to', DB::raw('COUNT(*) as cnt'))
                ->groupBy('assigned_to')
                ->orderByDesc('cnt')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    $user = User::query()->find($row->assigned_to);

                    return [
                        'name' => $user?->name ?? 'Technician #'.$row->assigned_to,
                        'score' => (int) $row->cnt,
                    ];
                })
                ->all();
        }

        return [
            'open_tickets' => $openTickets,
            'completed_tickets_month' => $completedMonth,
            'visits_today' => FieldVisit::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('scheduled_at', today())
                ->count(),
            'pending_visits' => FieldVisit::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->count(),
            'avg_resolution_hours' => round((float) ($avgResolutionHours ?? 0), 1),
            'ranking' => $ranking,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function leaveStats(int $tenantId): array
    {
        return [
            'pending' => EmployeeLeaveRequest::query()
                ->where('tenant_id', $tenantId)
                ->where('status', EmployeeLeaveRequest::STATUS_PENDING)
                ->count(),
            'approved_month' => EmployeeLeaveRequest::query()
                ->where('tenant_id', $tenantId)
                ->where('status', EmployeeLeaveRequest::STATUS_APPROVED)
                ->whereMonth('start_date', now()->month)
                ->count(),
        ];
    }

    /**
     * @param  array{open: int, completed_month: int, delayed: int, in_progress: int}  $tasks
     * @return array<string, mixed>
     */
    private function performanceSnapshot(int $tenantId, array $tasks): array
    {
        $active = max(1, Employee::query()->where('tenant_id', $tenantId)->where('is_active', true)->count());
        $markedToday = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('work_date', now()->toDateString())
            ->count();

        $totalTasks = $tasks['open'] + $tasks['completed_month'];

        return [
            'attendance_rate' => (int) round(($markedToday / $active) * 100),
            'task_completion_rate' => $totalTasks > 0
                ? (int) round(($tasks['completed_month'] / $totalTasks) * 100)
                : 0,
        ];
    }

    /**
     * @return list<array{month: string, count: int}>
     */
    private function employeeGrowth(int $tenantId): array
    {
        $rows = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $rows[] = [
                'month' => $d->format('M Y'),
                'count' => Employee::query()
                    ->where('tenant_id', $tenantId)
                    ->whereYear('join_date', $d->year)
                    ->whereMonth('join_date', $d->month)
                    ->count(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{month: string, net: float, status: string}>
     */
    private function payrollTrend(int $tenantId): array
    {
        return PayrollRun::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(6)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (PayrollRun $run) => [
                'month' => $run->periodLabel(),
                'net' => round((float) $run->total_net, 2),
                'status' => (string) $run->status,
            ])
            ->all();
    }

    /**
     * @return list<array{department: string, count: int}>
     */
    private function departmentBreakdown(int $tenantId): array
    {
        return Employee::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('department', DB::raw('COUNT(*) as cnt'))
            ->groupBy('department')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department ?: 'Unassigned',
                'count' => (int) $row->cnt,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentTimeline(int $tenantId, int $limit): array
    {
        $events = [];

        AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->with('employee:id,name')
            ->orderByDesc('work_date')
            ->limit(4)
            ->get()
            ->each(function (AttendanceRecord $r) use (&$events): void {
                $events[] = [
                    'type' => 'attendance',
                    'label' => ($r->employee?->name ?? 'Staff').' — '.ucfirst((string) $r->status),
                    'at' => $r->work_date?->format('M j, Y') ?? '—',
                    'sort' => $r->work_date?->timestamp ?? 0,
                ];
            });

        EmployeeLeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->with('employee:id,name')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->each(function (EmployeeLeaveRequest $l) use (&$events): void {
                $events[] = [
                    'type' => 'leave',
                    'label' => ($l->employee?->name ?? 'Staff').' — '.$l->leaveTypeLabel(),
                    'at' => $l->created_at?->format('M j, H:i') ?? '—',
                    'sort' => $l->created_at?->timestamp ?? 0,
                ];
            });

        PayrollRun::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('paid_at')
            ->limit(2)
            ->get()
            ->each(function (PayrollRun $run) use (&$events): void {
                $events[] = [
                    'type' => 'payroll',
                    'label' => $run->periodLabel().' — '.number_format((float) $run->total_net, 0).' BDT',
                    'at' => $run->paid_at?->format('M j, Y') ?? $run->periodLabel(),
                    'sort' => $run->paid_at?->timestamp ?? 0,
                ];
            });

        usort($events, fn ($a, $b) => ($b['sort'] ?? 0) <=> ($a['sort'] ?? 0));

        return array_slice(array_map(fn ($e) => [
            'type' => $e['type'],
            'label' => $e['label'],
            'at' => $e['at'],
        ], $events), 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingLeaveRequests(int $tenantId, int $limit): array
    {
        return EmployeeLeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('status', EmployeeLeaveRequest::STATUS_PENDING)
            ->with('employee:id,name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (EmployeeLeaveRequest $l) => [
                'employee' => $l->employee?->name,
                'type' => $l->leaveTypeLabel(),
                'dates' => $l->start_date?->format('M j').' – '.$l->end_date?->format('M j'),
                'url' => \App\Filament\Pages\HrLeaveManagementPage::getUrl(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentTasks(int $tenantId, int $limit): array
    {
        return InternalTask::query()
            ->where('tenant_id', $tenantId)
            ->with('assignee:id,name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (InternalTask $t) => [
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'assignee' => $t->assignee?->name,
                'due' => $t->due_at?->format('M j'),
                'url' => \App\Filament\Resources\InternalTaskResource::getUrl('edit', ['record' => $t->id]),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentFieldVisits(int $tenantId, int $limit): array
    {
        return FieldVisit::query()
            ->where('tenant_id', $tenantId)
            ->with(['assignee:id,name', 'ticket:id,subject'])
            ->orderByDesc('scheduled_at')
            ->limit($limit)
            ->get()
            ->map(fn (FieldVisit $v) => [
                'technician' => $v->assignee?->name,
                'status' => $v->status,
                'scheduled' => $v->scheduled_at?->format('M j, H:i'),
                'subject' => $v->ticket?->subject ?? 'Field visit',
                'has_gps' => $v->latitude !== null && $v->longitude !== null,
                'url' => $v->support_ticket_id
                    ? \App\Filament\Resources\SupportTicketResource::getUrl('edit', ['record' => $v->support_ticket_id])
                    : \App\Filament\Pages\FieldTechnicianCenter::getUrl(),
                'map_url' => ($v->latitude && $v->longitude)
                    ? \App\Filament\Pages\FiberPlantMap::getUrl().'?lat='.$v->latitude.'&lng='.$v->longitude
                    : \App\Filament\Pages\FiberPlantMap::getUrl(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function reportLinks(): array
    {
        return [
            ['label' => 'HR reports', 'url' => \App\Filament\Pages\HrReportsPage::getUrl(), 'icon' => 'chart-pie'],
            ['label' => 'Monthly payroll', 'url' => \App\Filament\Pages\HrPayrollGenerationPage::getUrl(), 'icon' => 'banknotes'],
            ['label' => 'Attendance log', 'url' => \App\Filament\Resources\AttendanceRecordResource::getUrl('index'), 'icon' => 'calendar-days'],
            ['label' => 'Leave management', 'url' => \App\Filament\Pages\HrLeaveManagementPage::getUrl(), 'icon' => 'sun'],
            ['label' => 'Salary policies', 'url' => \App\Filament\Pages\HrSalaryPoliciesPage::getUrl(), 'icon' => 'scale'],
            ['label' => 'Advance salary', 'url' => \App\Filament\Pages\HrAdvanceSalaryPage::getUrl(), 'icon' => 'hand-raised'],
            ['label' => 'Task board', 'url' => \App\Filament\Pages\TaskKanbanBoard::getUrl(), 'icon' => 'view-columns'],
            ['label' => 'Field technicians', 'url' => \App\Filament\Pages\FieldTechnicianCenter::getUrl(), 'icon' => 'wrench-screwdriver'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function gisLinks(): array
    {
        return [
            ['label' => 'GIS intelligence map', 'url' => \App\Filament\Pages\FiberPlantMap::getUrl(), 'icon' => 'map'],
            ['label' => 'Field technician center', 'url' => \App\Filament\Pages\FieldTechnicianCenter::getUrl(), 'icon' => 'wrench-screwdriver'],
            ['label' => 'Support hub', 'url' => \App\Filament\Pages\SupportHub::getUrl(), 'icon' => 'lifebuoy'],
            ['label' => 'Mobile apps hub', 'url' => \App\Filament\Pages\MobileAppsHub::getUrl(), 'icon' => 'device-phone-mobile'],
        ];
    }
}
