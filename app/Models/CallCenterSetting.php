<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CallCenterSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'websip_enabled',
        'sip_server',
        'wss_uri',
        'sip_domain',
        'default_extension',
        'outbound_caller_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'websip_enabled' => 'boolean',
            'meta' => 'array',
        ];
    }

    public static function forTenant(int $tenantId): self
    {
        return static::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['websip_enabled' => false],
        );
    }
}
