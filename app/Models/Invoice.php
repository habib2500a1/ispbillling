<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\CreatesFromTrustedSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant, CreatesFromTrustedSource;

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if (blank($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber((int) $invoice->tenant_id);
            }
        });
    }

    public static function generateInvoiceNumber(int $tenantId): string
    {
        $prefix = rtrim((string) config('billing.invoice_number_prefix', 'INV'), '-').'-';
        if (config('billing.invoice_number_year_infix', true)) {
            $prefix .= now()->format('Y').'-';
        }
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'issue_date',
        'due_date',
        'period_start',
        'period_end',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'amount_paid',
        'status',
        'notes',
        'sd_amount',
        'withholding_amount',
        'coupon_id',
        'coupon_discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'sd_amount' => 'decimal:2',
            'withholding_amount' => 'decimal:2',
            'coupon_discount_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function balanceDue(): float
    {
        return max(0.0, round((float) $this->total - (float) $this->amount_paid, 2));
    }

    public function isOverdue(?\Carbon\CarbonInterface $asOf = null): bool
    {
        $asOf ??= now();
        if (! in_array($this->status, ['open', 'partial'], true)) {
            return false;
        }

        return $this->balanceDue() > 0 && $asOf->toDateString() > $this->due_date->toDateString();
    }

    public function isPastGrace(?\Carbon\CarbonInterface $asOf = null): bool
    {
        return \App\Services\Billing\LateFeeCalculator::isPastGrace($this, $asOf);
    }
}

