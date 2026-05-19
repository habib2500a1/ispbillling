<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthUsageDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'bandwidth_usage_daily';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'usage_date',
        'bytes_in',
        'bytes_out',
        'peak_rate_in_bps',
        'peak_rate_out_bps',
        'online_seconds',
        'session_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'peak_rate_in_bps' => 'integer',
            'peak_rate_out_bps' => 'integer',
            'online_seconds' => 'integer',
            'session_count' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    public static function formatBps(?int $bps): string
    {
        return \App\Support\BandwidthDirection::formatBps($bps);
    }
}
