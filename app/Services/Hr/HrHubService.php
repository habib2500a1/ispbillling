<?php

namespace App\Services\Hr;

use App\Models\HrAttendanceLog;
use App\Models\HrLeaveRequest;
use App\Models\IspExpense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * HR Hub lite — staff roster + attendance + leave + salary expense snapshot.
 */
final class HrHubService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $today = Carbon::today();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $staff = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        $presentIds = HrAttendanceLog::query()
            ->whereDate('work_date', $today)
            ->whereIn('status', ['present', 'late', 'half_day'])
            ->pluck('user_id')
            ->all();

        $onLeaveIds = HrLeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $today)
            ->whereDate('to_date', '>=', $today)
            ->pluck('user_id')
            ->all();

        $roleCounts = [];
        foreach (Role::query()->orderBy('name')->get(['name']) as $role) {
            $roleCounts[$role->name] = User::role($role->name)->count();
        }

        $salaryMonth = (float) IspExpense::query()
            ->where('category', 'employee_salary')
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $attendanceToday = HrAttendanceLog::query()
            ->with('user:id,name,email,mobile')
            ->whereDate('work_date', $today)
            ->orderBy('clock_in_at')
            ->get()
            ->map(fn (HrAttendanceLog $log) => $this->attendanceRow($log))
            ->all();

        $pendingLeaves = HrLeaveRequest::query()
            ->with('user:id,name,email')
            ->where('status', 'pending')
            ->orderBy('from_date')
            ->limit(30)
            ->get()
            ->map(fn (HrLeaveRequest $leave) => $this->leaveRow($leave))
            ->all();

        $recentLeaves = HrLeaveRequest::query()
            ->with(['user:id,name', 'reviewer:id,name'])
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (HrLeaveRequest $leave) => $this->leaveRow($leave))
            ->all();

        $recentSalary = IspExpense::query()
            ->with('linkedUser:id,name')
            ->where('category', 'employee_salary')
            ->orderByDesc('expense_date')
            ->limit(10)
            ->get()
            ->map(fn (IspExpense $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'amount' => (float) $e->amount,
                'date' => optional($e->expense_date)?->format('Y-m-d'),
                'user' => $e->linkedUser?->name,
            ])
            ->all();

        return [
            'updated_at' => now()->toIso8601String(),
            'stats' => [
                'staff' => $staff->count(),
                'present_today' => count($presentIds),
                'on_leave_today' => count(array_unique($onLeaveIds)),
                'pending_leaves' => HrLeaveRequest::query()->where('status', 'pending')->count(),
                'salary_month' => round($salaryMonth, 2),
                'roles' => count($roleCounts),
            ],
            'role_counts' => $roleCounts,
            'staff' => $staff->map(function (User $u) use ($presentIds, $onLeaveIds) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'mobile' => $u->mobile,
                    'roles' => $u->roles->pluck('name')->all(),
                    'present_today' => in_array($u->id, $presentIds, true),
                    'on_leave_today' => in_array($u->id, $onLeaveIds, true),
                ];
            })->all(),
            'attendance_today' => $attendanceToday,
            'pending_leaves' => $pendingLeaves,
            'recent_leaves' => $recentLeaves,
            'recent_salary' => $recentSalary,
            'leave_types' => HrLeaveRequest::TYPES,
            'attendance_statuses' => HrAttendanceLog::STATUSES,
            'staff_options' => $staff->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->all(),
        ];
    }

    /**
     * @param  array{status?: string, notes?: string, clock_in?: bool}  $data
     */
    public function clockIn(int $userId, array $data = []): HrAttendanceLog
    {
        $today = Carbon::today();
        $existing = HrAttendanceLog::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $today)
            ->first();

        if ($existing && $existing->clock_in_at) {
            throw new InvalidArgumentException('Already clocked in today.');
        }

        $status = $data['status'] ?? 'present';
        if (! array_key_exists($status, HrAttendanceLog::STATUSES)) {
            $status = 'present';
        }

        // Late if after 10:00 local
        if ($status === 'present' && now()->format('H:i') > '10:00') {
            $status = 'late';
        }

        return HrAttendanceLog::query()->updateOrCreate(
            ['user_id' => $userId, 'work_date' => $today->toDateString()],
            [
                'clock_in_at' => now(),
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => Auth::id(),
            ]
        );
    }

    public function clockOut(int $userId): HrAttendanceLog
    {
        $log = HrAttendanceLog::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', Carbon::today())
            ->first();

        if (! $log || ! $log->clock_in_at) {
            throw new InvalidArgumentException('Clock in first.');
        }

        if ($log->clock_out_at) {
            throw new InvalidArgumentException('Already clocked out.');
        }

        $log->update(['clock_out_at' => now()]);

        return $log->fresh();
    }

    /**
     * @param  array{user_id: int, from_date: string, to_date: string, leave_type?: string, reason?: string}  $data
     */
    public function requestLeave(array $data): HrLeaveRequest
    {
        $from = Carbon::parse($data['from_date'])->startOfDay();
        $to = Carbon::parse($data['to_date'])->startOfDay();
        if ($to->lt($from)) {
            throw new InvalidArgumentException('To date must be on or after from date.');
        }

        $type = $data['leave_type'] ?? 'casual';
        if (! array_key_exists($type, HrLeaveRequest::TYPES)) {
            $type = 'casual';
        }

        return HrLeaveRequest::query()->create([
            'user_id' => (int) $data['user_id'],
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'leave_type' => $type,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function reviewLeave(int $id, string $status, ?string $adminNote = null): HrLeaveRequest
    {
        if (! in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid leave status.');
        }

        $leave = HrLeaveRequest::query()->findOrFail($id);
        if ($leave->status !== 'pending' && $status !== 'cancelled') {
            throw new InvalidArgumentException('Only pending leaves can be reviewed.');
        }

        $leave->update([
            'status' => $status,
            'admin_note' => $adminNote,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $leave->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceRow(HrAttendanceLog $log): array
    {
        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'name' => $log->user?->name ?? '—',
            'status' => $log->status,
            'status_label' => $log->status_label,
            'clock_in' => optional($log->clock_in_at)?->format('H:i'),
            'clock_out' => optional($log->clock_out_at)?->format('H:i'),
            'notes' => $log->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leaveRow(HrLeaveRequest $leave): array
    {
        return [
            'id' => $leave->id,
            'user_id' => $leave->user_id,
            'name' => $leave->user?->name ?? '—',
            'from' => $leave->from_date?->format('Y-m-d'),
            'to' => $leave->to_date?->format('Y-m-d'),
            'days' => $leave->days,
            'type' => $leave->leave_type,
            'type_label' => $leave->type_label,
            'status' => $leave->status,
            'status_label' => $leave->status_label,
            'reason' => $leave->reason,
            'reviewer' => $leave->reviewer?->name,
        ];
    }
}
