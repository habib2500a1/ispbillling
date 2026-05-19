<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorVisit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'collector_id',
        'customer_id',
        'payment_id',
        'invoice_id',
        'amount_collected',
        'payment_method',
        'latitude',
        'longitude',
        'accuracy_meters',
        'location_text',
        'notes',
        'device_meta',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_collected' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
            'device_meta' => 'array',
            'visited_at' => 'datetime',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
