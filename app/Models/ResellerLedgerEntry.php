<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerLedgerEntry extends Model
{
    use BelongsToTenant;

    public const TYPE_ADMIN_RECEIVABLE_ACCRUAL = 'admin_receivable_accrual';

    public const TYPE_ADMIN_RECEIVABLE_COLLECTION = 'admin_receivable_collection';

    public const TYPE_ADMIN_RECEIVABLE_SETTLEMENT = 'admin_receivable_settlement';

    public const TYPE_MARGIN_ACCRUAL = 'margin_accrual';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPE_DEBIT_NOTE = 'debit_note';

    public const DIRECTION_DEBIT = 'debit';

    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'customer_id',
        'invoice_id',
        'payment_id',
        'entry_type',
        'direction',
        'amount',
        'admin_receivable_after',
        'retail_amount',
        'wholesale_amount',
        'margin_amount',
        'reference',
        'notes',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'admin_receivable_after' => 'decimal:2',
            'retail_amount' => 'decimal:2',
            'wholesale_amount' => 'decimal:2',
            'margin_amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
