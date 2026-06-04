<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ResellerPortalPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResellerCustomRole extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'name',
        'permissions',
        'menu_permissions',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'menu_permissions' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ResellerStaff::class);
    }

    /**
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        $perms = $this->permissions ?? [];

        return array_values(array_intersect($perms, ResellerPortalPermission::all()));
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }
}
