<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\CreatesFromTrustedSource;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToTenant, CreatesFromTrustedSource;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (blank($payment->receipt_number)) {
                $payment->receipt_number = static::generateReceiptNumber((int) ($payment->tenant_id ?? 1));
            }
            if (blank($payment->gateway) && filled($payment->method)) {
                $payment->gateway = in_array($payment->method, PaymentGateway::webhookGateways(), true)
                    ? $payment->method
                    : null;
            }
        });
    }

    public static function generateReceiptNumber(int $tenantId): string
    {
        $prefix = rtrim((string) config('payments.receipt_prefix', 'RCP'), '-').'-';
        if (config('payments.receipt_year_infix', true)) {
            $prefix .= now()->format('Y').'-';
        }
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('receipt_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'invoice_id',
        'payment_type',
        'parent_payment_id',
        'recorded_by',
        'amount',
        'method',
        'reference',
        'notes',
        'status',
        'paid_at',
        'meta',
        'proof_path',
        'gateway',
        'gateway_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'parent_payment_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function methodLabel(): string
    {
        return PaymentGateway::label((string) $this->method);
    }

    public function typeLabel(): string
    {
        return PaymentType::label((string) ($this->payment_type ?? PaymentType::PAYMENT));
    }

    public function isRefund(): bool
    {
        return ($this->payment_type ?? PaymentType::PAYMENT) === PaymentType::REFUND;
    }

    public function displayAmount(): float
    {
        return $this->isRefund() ? -1 * (float) $this->amount : (float) $this->amount;
    }
}
