<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfsSmsRecord extends Model
{
    use BelongsToTenant;

    public const STATUS_AWAITING = 'awaiting_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_USED = 'used';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'device_name',
        'gateway',
        'sender_type',
        'sender_phone',
        'merchant_phone',
        'transaction_id',
        'amount',
        'balance_after',
        'status',
        'matched_pending_id',
        'payment_id',
        'raw_message',
        'sms_received_at',
        'used_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'sms_received_at' => 'datetime',
            'used_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function pending(): BelongsTo
    {
        return $this->belongsTo(PendingGatewayPayment::class, 'matched_pending_id');
    }

    public function resolveMatchedCustomer(): ?Customer
    {
        if ($this->relationLoaded('payment') && $this->payment?->customer !== null) {
            return $this->payment->customer;
        }

        if ($this->payment_id !== null) {
            $this->loadMissing('payment.customer');

            return $this->payment?->customer;
        }

        $customerId = $this->meta['matched_customer_id'] ?? null;
        if ($customerId === null) {
            return null;
        }

        return Customer::withoutGlobalScopes()->find((int) $customerId);
    }

    public function enrichMatchedCustomerMeta(?Customer $customer = null): void
    {
        $customer ??= $this->resolveMatchedCustomer();
        if ($customer === null) {
            return;
        }

        $this->forceFill([
            'meta' => array_merge($this->meta ?? [], \App\Support\MfsSmsCustomerSnapshot::from($customer)),
        ])->save();
    }
}
