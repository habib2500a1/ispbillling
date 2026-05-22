<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLineActivation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'device_id',
        'invoice_id',
        'wallet_payment_id',
        'performed_by',
        'line_charge',
        'device_charge',
        'total_charged',
        'wallet_applied',
        'cash_collected',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'line_charge' => 'decimal:2',
            'device_charge' => 'decimal:2',
            'total_charged' => 'decimal:2',
            'wallet_applied' => 'decimal:2',
            'cash_collected' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function walletPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'wallet_payment_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
