<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvanceSalaryRequest extends Model
{
    use BelongsToTenant;

    public const RETURN_NEXT_SALARY = 'next_salary';

    public const RETURN_INSTALLMENT = 'installment';

    public const RETURN_MANUAL = 'manual';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DEDUCTED = 'deducted';

    public const STATUS_RECOVERED = 'recovered';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'amount',
        'request_date',
        'purpose',
        'return_type',
        'deduction_month',
        'status',
        'created_by',
        'deducted_at',
        'payroll_run_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_date' => 'date',
            'deduction_month' => 'date',
            'deducted_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function returnTypeLabel(): string
    {
        return match ($this->return_type) {
            self::RETURN_INSTALLMENT => 'Installment (multiple months)',
            self::RETURN_MANUAL => 'Manual recovery',
            default => '1-Time Deduction (Next Salary)',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DEDUCTED => 'Deducted from payroll',
            self::STATUS_RECOVERED => 'Recovered',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Approved / outstanding',
        };
    }

    public function deductionMonthLabel(): string
    {
        return $this->deduction_month?->format('F Y') ?? '—';
    }
}
