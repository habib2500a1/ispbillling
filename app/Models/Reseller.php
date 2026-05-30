<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait, BelongsToTenant, HasApiTokens;

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
            ->whereNotNull('portal_password')
            ->where(function ($q) use ($login, $digits): void {
                $q->where('code', $login)
                    ->orWhere('portal_login', $login)
                    ->orWhere('email', $login);
                if ($digits !== '') {
                    $q->orWhere('phone', $digits)->orWhere('phone', $login);
                } else {
                    $q->orWhere('phone', $login);
                }
            })
            ->first();
    }

    protected static function booted(): void
    {
        static::creating(function (Reseller $reseller): void {
            if (blank($reseller->code)) {
                $reseller->code = static::generateCode((int) $reseller->tenant_id);
            }
        });
    }

    public static function generateCode(int $tenantId): string
    {
        $prefix = 'RSL-'.now()->format('ym').'-';
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('code');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'franchise_type',
        'name',
        'code',
        'client_id_prefix',
        'phone',
        'email',
        'portal_login',
        'primary_user_id',
        'address',
        'city',
        'district',
        'trade_license',
        'nid_number',
        'portal_password',
        'portal_last_login_at',
        'contact_person',
        'commission_type',
        'commission_value',
        'revenue_share_percent',
        'white_label_enabled',
        'own_integrations_enabled',
        'brand_name',
        'brand_logo_path',
        'brand_primary_color',
        'portal_subdomain',
        'wallet_balance',
        'max_clients',
        'max_active_clients',
        'wallet_frozen',
        'is_active',
        'notes',
        'meta',
        'portal_permissions',
        'auto_invoice_enabled',
        'auto_suspend_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'portal_devices',
    ];

    protected $hidden = [
        'portal_password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function getAuthPassword(): string
    {
        return (string) ($this->portal_password ?? '');
    }

    public function hasPortalAccess(): bool
    {
        return filled($this->portal_password) && $this->is_active;
    }

    protected function casts(): array
    {
        return [
            'portal_last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'white_label_enabled' => 'boolean',
            'own_integrations_enabled' => 'boolean',
            'commission_value' => 'decimal:2',
            'revenue_share_percent' => 'decimal:2',
            'wallet_balance' => 'decimal:2',
            'wallet_frozen' => 'boolean',
            'max_clients' => 'integer',
            'max_active_clients' => 'integer',
            'meta' => 'array',
            'portal_permissions' => 'array',
            'auto_invoice_enabled' => 'boolean',
            'auto_suspend_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'portal_devices' => 'array',
        ];
    }

    public function requiresTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return list<string>
     */
    public function portalPermissions(): array
    {
        $custom = $this->portal_permissions;
        if (is_array($custom) && $custom !== []) {
            return array_values(array_intersect($custom, ResellerPortalPermission::all()));
        }

        return ResellerPortalPermission::defaultsFor((string) ($this->franchise_type ?: ResellerType::RESELLER));
    }

    public function canPortal(string $permission): bool
    {
        return in_array($permission, $this->portalPermissions(), true);
    }

    public function displayName(): string
    {
        return filled($this->brand_name) ? (string) $this->brand_name : (string) $this->name;
    }

    public function brandInitial(): string
    {
        $name = trim($this->displayName());

        return $name !== ''
            ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'))
            : 'R';
    }

    public function logoUrl(): ?string
    {
        return \App\Support\ResellerBranding::logoUrlForReseller($this);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'parent_id');
    }

    public function primaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_user_id');
    }

    public function portalLoginId(): string
    {
        return (string) ($this->portal_login ?: $this->code);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ResellerStaff::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Reseller::class, 'parent_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function territories(): HasMany
    {
        return $this->hasMany(ResellerTerritory::class);
    }

    public function resellerPackages(): HasMany
    {
        return $this->hasMany(ResellerPackage::class);
    }

    public function balanceTransfersIn(): HasMany
    {
        return $this->hasMany(ResellerBalanceTransfer::class, 'to_reseller_id');
    }

    public function balanceTransfersOut(): HasMany
    {
        return $this->hasMany(ResellerBalanceTransfer::class, 'from_reseller_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ResellerCommission::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(ResellerSettlement::class);
    }

    public function walletRechargeRequests(): HasMany
    {
        return $this->hasMany(ResellerWalletRechargeRequest::class);
    }

    public function portalActivityLogs(): HasMany
    {
        return $this->hasMany(ResellerPortalActivityLog::class);
    }

    public function isSubReseller(): bool
    {
        return $this->parent_id !== null || $this->franchise_type === ResellerType::SUB_RESELLER;
    }

    public function franchiseTypeLabel(): string
    {
        return ResellerType::labels()[$this->franchise_type] ?? ucfirst((string) $this->franchise_type);
    }

    public function commissionLabel(): string
    {
        if ($this->commission_type === 'fixed') {
            return number_format((float) $this->commission_value, 2).' BDT fixed';
        }

        return number_format((float) $this->commission_value, 2).'%';
    }

    /**
     * @return array{customers: int, sub_resellers: int, pending_commission: float, wallet: float}
     */
    public function dashboardStats(): array
    {
        return [
            'customers' => $this->customers()->count(),
            'sub_resellers' => $this->children()->count(),
            'pending_commission' => (float) $this->commissions()->where('status', ResellerCommission::STATUS_PENDING)->sum('commission_amount'),
            'wallet' => (float) $this->wallet_balance,
        ];
    }
}
