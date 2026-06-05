<?php

namespace App\Services\Accounting;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\Hr\EmployeeAdvanceSalaryService;
use App\Services\Hr\HrPolicySettings;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly EmployeeAdvanceSalaryService $advanceSalary,
    ) {}

    /**
     * @param  array{bonus?: string}  $options  none|five_percent|ten_percent
     */
    public function generateDraft(int $month, int $year, ?int $tenantId = null, array $options = []): PayrollRun
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $policy = HrPolicySettings::get();
        $bonusMode = (string) ($options['bonus'] ?? 'none');

        return DB::transaction(function () use ($month, $year, $tenantId, $policy, $bonusMode) {
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
                $line = $this->calculateLine($employee, $month, $year, $policy, $bonusMode);
                $advanceDed = $line['advance'];

                if ($advanceDed > 0) {
                    $wallet = (float) $employee->fresh()->wallet_balance;
                    if ($wallet > 0) {
                        $employee->decrement('wallet_balance', min($advanceDed, $wallet));
                    }
                }

                PayrollItem::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'basic_salary' => $line['basic'],
                    'auto_deductions' => $line['auto'],
                    'allowances' => $line['allowances'],
                    'manual_deduction' => $line['manual'],
                    'bonus_amount' => $line['bonus'],
                    'gross_salary' => $line['basic'],
                    'deductions' => round($line['auto'] + $line['manual'], 2),
                    'net_salary' => $line['net'],
                    'amount_due' => $line['net'],
                    'payment_status' => 'pending',
                    'notes' => $line['notes'],
                ]);

                $gross += $line['basic'];
                $deductions += $line['auto'] + $line['manual'];
                $net += $line['net'];
            }

            $run->update([
                'total_gross' => $gross,
                'total_deductions' => $deductions,
                'total_net' => $net,
            ]);

            $this->advanceSalary->markDeductionsForRun($run->fresh());

            return $run->fresh(['items.employee']);
        });
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array{basic: float, late: float, absent: float, advance: float, pf: float, auto: float, bonus: float, allowances: float, manual: float, net: float, notes: string}
     */
    public function calculateLine(Employee $employee, int $month, int $year, array $policy, string $bonusMode = 'none'): array
    {
        $basic = round((float) $employee->base_salary, 2);
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $absentDays = $records->where('status', 'absent')->count();
        $lateDays = $this->countLateDays($records, $policy);
        $allowedLate = (int) ($policy['allowed_late_days'] ?? 3);
        $excessLate = max(0, $lateDays - $allowedLate);
        $lateFine = round($excessLate * (float) ($policy['late_fine_amount'] ?? 50), 2);

        if ($lateDays >= (int) ($policy['late_salary_cut_trigger_days'] ?? 6)) {
            $lateFine = round($lateFine + ($basic / 26), 2);
        }

        $absentDed = round($absentDays * ($basic / 26) * ((float) ($policy['absent_day_deduction_percent'] ?? 100) / 100), 2);
        $advanceDed = $this->advanceSalary->advanceDeductionForEmployee($employee, $month, $year);
        $pf = round($basic * ((float) ($policy['pf_percent'] ?? 5) / 100), 2);
        $auto = round($lateFine + $absentDed + $advanceDed + $pf, 2);

        $bonus = match ($bonusMode) {
            'five_percent' => round($basic * 0.05, 2),
            'ten_percent' => round($basic * 0.10, 2),
            default => 0.0,
        };
        $allowances = $bonus;
        $manual = 0.0;
        $net = round(max(0, $basic + $allowances - $auto - $manual), 2);

        $notes = sprintf(
            'Late %d · Absent %d · ADV %.2f · PF %.2f',
            $lateDays,
            $absentDays,
            $advanceDed,
            $pf,
        );

        return [
            'basic' => $basic,
            'late' => $lateFine,
            'absent' => $absentDed,
            'advance' => $advanceDed,
            'pf' => $pf,
            'auto' => $auto,
            'bonus' => $bonus,
            'allowances' => $allowances,
            'manual' => $manual,
            'net' => $net,
            'notes' => $notes,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AttendanceRecord>  $records
     * @param  array<string, mixed>  $policy
     */
    private function countLateDays($records, array $policy): int
    {
        $startTime = (string) ($policy['office_start_time'] ?? '09:00');
        $grace = (int) ($policy['late_grace_minutes'] ?? 10);
        [$h, $m] = array_pad(explode(':', $startTime), 2, 0);
        $threshold = ((int) $h * 60) + (int) $m + $grace;
        $late = 0;

        foreach ($records as $record) {
            if ($record->status !== 'present' || blank($record->check_in)) {
                continue;
            }
            $parts = explode(':', (string) $record->check_in);
            $mins = ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
            if ($mins > $threshold) {
                $late++;
            }
        }

        return $late;
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

        $run->items()->update(['payment_status' => 'paid', 'amount_due' => 0]);

        return $run->fresh();
    }
}
