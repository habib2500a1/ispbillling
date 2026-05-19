<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotificationLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'event',
        'channel',
        'recipient',
        'status',
        'message',
        'error',
        'meta',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function smsDeliveryReport(): HasOne
    {
        return $this->hasOne(SmsDeliveryReport::class);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'sent' => 'success',
            'failed' => 'danger',
            'skipped' => 'gray',
            default => 'warning',
        };
    }
}
