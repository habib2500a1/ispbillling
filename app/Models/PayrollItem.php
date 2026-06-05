<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_salary',
        'auto_deductions',
        'allowances',
        'manual_deduction',
        'bonus_amount',
        'gross_salary',
        'deductions',
        'net_salary',
        'amount_due',
        'payment_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'auto_deductions' => 'decimal:2',
            'allowances' => 'decimal:2',
            'manual_deduction' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'amount_due' => 'decimal:2',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
