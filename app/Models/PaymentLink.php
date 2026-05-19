<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    use BelongsToTenant;

    public const PURPOSE_INVOICE = 'invoice';

    public const PURPOSE_WALLET = 'wallet';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'invoice_id',
        'created_by',
        'token',
        'purpose',
        'source_event',
        'amount',
        'expires_at',
        'used_at',
        'sms_sent_at',
        'access_count',
        'first_clicked_at',
        'converted_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'sms_sent_at' => 'datetime',
            'first_clicked_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    public function publicUrl(): string
    {
        return route('bill-payment.link', ['token' => $this->token]);
    }

    public static function generateToken(): string
    {
        return Str::lower(Str::random(48));
    }
}
