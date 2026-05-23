<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalPrediction extends Model
{
    use BelongsToTenant;

    public const LEVEL_NORMAL = 'normal';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_CRITICAL = 'critical';

    public const LEVEL_EMERGENCY = 'emergency';

    protected $fillable = [
        'tenant_id',
        'device_id',
        'olt_id',
        'scope',
        'risk_score',
        'risk_level',
        'prediction_type',
        'summary',
        'factors',
        'predicted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'factors' => 'array',
            'predicted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'olt_id');
    }
}
