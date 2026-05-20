<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MikrotikServer extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saved(function (MikrotikServer $server): void {
            \App\Services\Radius\RadiusNasResolver::forgetCache((int) $server->tenant_id);
        });

        static::deleted(function (MikrotikServer $server): void {
            \App\Services\Radius\RadiusNasResolver::forgetCache((int) $server->tenant_id);
        });
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'host',
        'radius_nas_ip',
        'api_port',
        'use_ssl',
        'legacy_login',
        'api_username',
        'api_password',
        'default_ppp_password',
        'ppp_profile_default',
        'is_enabled',
        'last_api_status',
        'last_error',
        'last_checked_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'use_ssl' => 'boolean',
            'legacy_login' => 'boolean',
            'is_enabled' => 'boolean',
            'api_password' => 'encrypted',
            'default_ppp_password' => 'encrypted',
            'last_checked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
