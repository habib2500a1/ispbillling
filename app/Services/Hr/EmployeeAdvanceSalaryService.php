<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeAdvanceSalaryRequest;
use App\Models\PayrollRun;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;

final class EmployeeAdvanceSalaryService
{
    /**
     * @param  array{
     *   employee_id: int,
     *   amount: float|int|string,
     *   request_date: string,
     *   purpose?: ?string,
     *   return_type: string,
     *   deduction_month: string,
     * }  $data
     */
    public function createRequest(array $data, ?int $createdBy = null): EmployeeAdvanceSalaryRequest
    {
        $tenantId = TenantResolver::requiredTenantId();
        $amount = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($data, $createdBy, $tenantId, $amount): EmployeeAdvanceSalaryRequest {
            $employee = Employee::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail((int) $data['employee_id']);

            $request = EmployeeAdvanceSalaryRequest::query()->create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'amount' => $amount,
                'request_date' => $data['request_date'],
                'purpose' => $data['purpose'] ?? null,
                'return_type' => $data['return_type'],
                'deduction_month' => $this->normalizeDeductionMonth($data['deduction_month']),
                'status' => EmployeeAdvanceSalaryRequest::STATUS_APPROVED,
                'created_by' => $createdBy,
            ]);

            $employee->increment('wallet_balance', $amount);

            return $request->load('employee');
        });
    }

    public function recover(EmployeeAdvanceSalaryRequest $request, float $amount): EmployeeAdvanceSalaryRequest
    {
        $amount = round(min($amount, (float) $request->amount), 2);

        return DB::transaction(function () use ($request, $amount): EmployeeAdvanceSalaryRequest {
            $employee = $request->employee;
            $deduct = min($amount, (float) $employee->wallet_balance);
            $employee->decrement('wallet_balance', $deduct);

            $request->update([
                'status' => EmployeeAdvanceSalaryRequest::STATUS_RECOVERED,
            ]);

            return $request->fresh(['employee']);
        });
    }

    /**
     * Sum approved advances scheduled for payroll deduction in this period.
     */
    public function advanceDeductionForEmployee(Employee $employee, int $month, int $year): float
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);

        return (float) EmployeeAdvanceSalaryRequest::query()
            ->where('employee_id', $employee->id)
            ->where('return_type', EmployeeAdvanceSalaryRequest::RETURN_NEXT_SALARY)
            ->where('status', EmployeeAdvanceSalaryRequest::STATUS_APPROVED)
            ->whereDate('deduction_month', $monthStart)
            ->sum('amount');
    }

    public function markDeductionsForRun(PayrollRun $run): void
    {
        $monthStart = sprintf('%04d-%02d-01', $run->period_year, $run->period_month);

        EmployeeAdvanceSalaryRequest::query()
            ->where('return_type', EmployeeAdvanceSalaryRequest::RETURN_NEXT_SALARY)
            ->where('status', EmployeeAdvanceSalaryRequest::STATUS_APPROVED)
            ->whereDate('deduction_month', $monthStart)
            ->whereIn('employee_id', $run->items()->pluck('employee_id'))
            ->update([
                'status' => EmployeeAdvanceSalaryRequest::STATUS_DEDUCTED,
                'deducted_at' => now(),
                'payroll_run_id' => $run->id,
            ]);
    }

    private function normalizeDeductionMonth(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value.'-01';
        }

        return date('Y-m-01', strtotime($value));
    }
}
