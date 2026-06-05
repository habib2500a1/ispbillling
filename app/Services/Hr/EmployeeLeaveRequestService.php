<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\EmployeeLeaveRequest;
use App\Support\TenantResolver;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

final class EmployeeLeaveRequestService
{
    /**
     * @param  array{
     *   employee_id: int,
     *   leave_type: string,
     *   start_date: string,
     *   end_date: string,
     *   reason?: ?string,
     *   status?: string,
     * }  $data
     */
    public function create(array $data, ?int $approvedBy = null): EmployeeLeaveRequest
    {
        $tenantId = TenantResolver::requiredTenantId();
        $status = $data['status'] ?? EmployeeLeaveRequest::STATUS_APPROVED;

        return DB::transaction(function () use ($data, $approvedBy, $tenantId, $status): EmployeeLeaveRequest {
            $request = EmployeeLeaveRequest::query()->create([
                'tenant_id' => $tenantId,
                'employee_id' => (int) $data['employee_id'],
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'] ?? null,
                'status' => $status,
                'approved_by' => $status === EmployeeLeaveRequest::STATUS_APPROVED ? $approvedBy : null,
                'approved_at' => $status === EmployeeLeaveRequest::STATUS_APPROVED ? now() : null,
            ]);

            if ($request->status === EmployeeLeaveRequest::STATUS_APPROVED) {
                $this->syncAttendanceForLeave($request);
            }

            return $request->load('employee');
        });
    }

    public function approve(EmployeeLeaveRequest $request, ?int $userId = null): EmployeeLeaveRequest
    {
        $request->update([
            'status' => EmployeeLeaveRequest::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->syncAttendanceForLeave($request->fresh());

        return $request->fresh(['employee']);
    }

    public function syncAttendanceForLeave(EmployeeLeaveRequest $request): void
    {
        $status = $request->leave_type === 'unpaid' ? 'absent' : 'leave';

        foreach (CarbonPeriod::create($request->start_date, $request->end_date) as $day) {
            AttendanceRecord::query()->updateOrCreate(
                [
                    'tenant_id' => $request->tenant_id,
                    'employee_id' => $request->employee_id,
                    'work_date' => $day->toDateString(),
                ],
                [
                    'status' => $status,
                    'notes' => 'Leave: '.$request->leaveTypeLabel().($request->reason ? ' — '.$request->reason : ''),
                ],
            );
        }
    }
}
