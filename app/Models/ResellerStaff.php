<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ResellerPortalPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerStaff extends Model
{
    use BelongsToTenant;

    protected $table = 'reseller_staff';

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'name',
        'login',
        'email',
        'phone',
        'password',
        'portal_permissions',
        'meta',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'portal_permissions' => 'array',
            'meta' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function findForPortalLogin(string $login): ?self
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $login) ?? '';

        return static::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($q) use ($login, $digits): void {
                $q->where('login', $login)->orWhere('email', $login);
                if ($digits !== '') {
                    $q->orWhere('phone', $digits)->orWhere('phone', $login);
                } else {
                    $q->orWhere('phone', $login);
                }
            })
            ->first();
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    /**
     * @return list<string>
     */
    public function portalPermissions(): array
    {
        $custom = $this->portal_permissions;
        if (! is_array($custom) || $custom === []) {
            return $this->defaultPermissions();
        }

        return array_values(array_intersect($custom, ResellerPortalPermission::assignableToStaff()));
    }

    /**
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        $reseller = $this->relationLoaded('reseller') ? $this->reseller : $this->reseller()->first();
        $defaults = [
            ResellerPortalPermission::CUSTOMER_VIEW,
            ResellerPortalPermission::BILLING_VIEW,
            ResellerPortalPermission::PAYMENT_COLLECT,
        ];

        if ($reseller === null) {
            return $defaults;
        }

        return array_values(array_intersect($defaults, $reseller->portalPermissions()));
    }

    public function canPortal(string $permission): bool
    {
        if ($permission === ResellerPortalPermission::STAFF_MANAGE) {
            return false;
        }

        return in_array($permission, $this->portalPermissions(), true);
    }

    public function passwordPlain(): ?string
    {
        $plain = $this->meta['portal_password_plain'] ?? null;

        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    public function setPassword(string $plain): void
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $meta['portal_password_plain'] = $plain;

        $this->forceFill([
            'password' => $plain,
            'meta' => $meta,
        ])->saveQuietly();
    }

    public function recordLogin(): void
    {
        $this->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
