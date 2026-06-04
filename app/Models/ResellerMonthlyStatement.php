<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerMonthlyStatement extends Model
{
    use BelongsToTenant;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'period_year',
        'period_month',
        'opening_admin_due',
        'accruals',
        'collections_applied',
        'settlements',
        'closing_admin_due',
        'margin_total',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_admin_due' => 'decimal:2',
            'accruals' => 'decimal:2',
            'collections_applied' => 'decimal:2',
            'settlements' => 'decimal:2',
            'closing_admin_due' => 'decimal:2',
            'margin_total' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }
}
