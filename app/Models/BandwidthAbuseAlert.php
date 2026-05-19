<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthAbuseAlert extends Model
{
    use BelongsToTenant;

    public const TYPE_EXCESSIVE_DAILY = 'excessive_daily_usage';

    public const TYPE_SPEED_BURST = 'speed_burst';

    public const TYPE_CONCURRENT_SESSIONS = 'concurrent_sessions';

    public const TYPE_MAC_MISMATCH = 'mac_mismatch';

    public const TYPE_FUP_EXCEEDED = 'fup_exceeded';

    public const TYPE_NETFLOW_HIGH = 'netflow_high_usage';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'alert_type',
        'severity',
        'message',
        'meta',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_EXCESSIVE_DAILY => 'Excessive daily usage',
            self::TYPE_SPEED_BURST => 'Speed cap exceeded',
            self::TYPE_CONCURRENT_SESSIONS => 'Multiple sessions',
            self::TYPE_MAC_MISMATCH => 'MAC / device mismatch',
            self::TYPE_FUP_EXCEEDED => 'Fair usage policy',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
