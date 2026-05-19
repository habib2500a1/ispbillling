<?php

namespace App\Models;

use App\Support\Rbac\IspRoleTemplates;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $guard = auth('web');
            // Never call $guard->user() while the guard is still resolving the session user:
            // User::query() inside retrieveById() would recurse until memory is exhausted.
            if (! $guard->hasUser()) {
                return;
            }
            $user = $guard->user();
            if ($user->hasRole('super-admin')) {
                return;
            }
            if ($user->tenant_id !== null) {
                $builder->where('users.tenant_id', (int) $user->tenant_id);
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'email',
        'password',
        'is_active',
        'allowed_ips',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'allowed_ips' => 'array',
            'dashboard_preferences' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'last_login_at' => 'datetime',
            'last_logout_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $legacyRoles = [
            'isp-admin',
            'isp-support',
            'isp-engineer',
            'isp-manager',
        ];

        $staffRoles = array_diff(array_keys(IspRoleTemplates::all()), ['customer']);

        return $this->hasAnyRole(array_merge($staffRoles, $legacyRoles));
    }
}
