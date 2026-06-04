<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerPortalLoginLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'reseller_staff_id',
        'login_id',
        'success',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'failure_reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(ResellerStaff::class, 'reseller_staff_id');
    }
}
