<?php

namespace App\Services\Accounting;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function generateDraft(int $month, int $year, ?int $tenantId = null): PayrollRun
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return DB::transaction(function () use ($month, $year, $tenantId) {
            $run = PayrollRun::firstOrCreate(
                ['tenant_id' => $tenantId, 'period_month' => $month, 'period_year' => $year],
                ['status' => 'draft']
            );

            if ($run->status !== 'draft') {
                return $run->load('items.employee');
            }

            $run->items()->delete();

            $employees = Employee::query()->where('is_active', true)->get();
            $gross = 0.0;
            $deductions = 0.0;
            $net = 0.0;

            foreach ($employees as $employee) {
                $grossSalary = (float) $employee->base_salary;
                $ded = round($grossSalary * 0.05, 2);
                $netSalary = round($grossSalary - $ded, 2);

                PayrollItem::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'gross_salary' => $grossSalary,
                    'deductions' => $ded,
                    'net_salary' => $netSalary,
                    'notes' => 'Auto-generated (5% deduction placeholder)',
                ]);

                $gross += $grossSalary;
                $deductions += $ded;
                $net += $netSalary;
            }

            $run->update([
                'total_gross' => $gross,
                'total_deductions' => $deductions,
                'total_net' => $net,
            ]);

            return $run->fresh(['items.employee']);
        });
    }

    public function markPaid(PayrollRun $run, string $paymentMethod = 'bank', ?int $bankAccountId = null): PayrollRun
    {
        if ($run->status === 'paid') {
            return $run;
        }

        $payrollCode = config('accounting.payroll_expense_code', '5100');
        $cashCode = config('accounting.cash_account_code', '1000');
        $bankCode = config('accounting.bank_account_code', '1100');
        $creditCode = $paymentMethod === 'cash' ? $cashCode : $bankCode;
        $amount = (float) $run->total_net;

        $journal = $this->ledger->post(
            'Payroll '.$run->periodLabel(),
            [
                ['account_code' => $payrollCode, 'debit' => $amount],
                [
                    'account_code' => $creditCode,
                    'credit' => $amount,
                    'bank_account_id' => $bankAccountId,
                ],
            ],
            now(),
            'payroll',
            $run->id,
            (int) $run->tenant_id,
        );

        $run->update([
            'status' => 'paid',
            'paid_at' => now(),
            'journal_entry_id' => $journal->id,
        ]);

        return $run->fresh();
    }
}
