<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformInvoice extends Model
{
    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'billing_period',
        'plan_key',
        'plan_name',
        'customer_count',
        'max_customers',
        'amount',
        'status',
        'issue_date',
        'due_date',
        'paid_at',
        'payment_token',
        'payment_reference',
        'gateway',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PlatformInvoice $invoice): void {
            if (blank($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }
            if (blank($invoice->payment_token)) {
                $invoice->payment_token = strtolower(\Illuminate\Support\Str::random(48));
            }
        });
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'PLT-'.now()->format('Y').'-';
        $last = static::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markOverdueIfNeeded(): void
    {
        if ($this->isPaid() || $this->status === self::STATUS_VOID) {
            return;
        }

        if ($this->due_date !== null && $this->due_date->isPast()) {
            $this->forceFill(['status' => self::STATUS_OVERDUE])->save();
        }
    }
}
