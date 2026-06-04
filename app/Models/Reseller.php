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

        static::saved(function (Reseller $reseller): void {
            if ($reseller->wasChanged(['parent_id', 'id'])) {
                app(\App\Services\Resellers\ResellerHierarchyService::class)->syncPath($reseller);
                $reseller->children()->each(
                    fn (Reseller $child) => app(\App\Services\Resellers\ResellerHierarchyService::class)->syncPath($child),
                );
            }

            if (! $reseller->wasChanged('is_active')) {
                return;
            }

            app(\App\Services\Resellers\ResellerSubscriberSyncService::class)->handleResellerActiveChange(
                $reseller,
                (bool) $reseller->getOriginal('is_active'),
                (bool) $reseller->is_active,
            );
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
        'bonus_wallet_balance',
        'credit_limit',
        'billing_settlement_mode',
        'admin_receivable_due',
        'margin_accrued_total',
        'due_grace_period_days',
        'reseller_suspend_policy',
        'customer_billing_policy',
        'new_customer_charge_mode',
        'default_customer_billing_mode',
        'default_prepaid_grace_days',
        'default_postpaid_grace_days',
        'reseller_can_override_charge_mode',
        'allow_overdue_customers_active',
        'suspend_reseller_customers_on_breach',
        'risk_score',
        'billing_policy_evaluated_at',
        'low_balance_threshold',
        'auto_suspend_on_low_balance',
        'auto_restore_on_recharge',
        'portal_custom_domain',
        'brand_secondary_color',
        'portal_login_message',
        'hierarchy_path',
        'hierarchy_depth',
        'max_onu',
        'max_olt',
        'bandwidth_quota_mbps',
        'max_packages',
        'commission_mode',
        'allowed_ips',
        'api_access_enabled',
        'api_rate_limit_per_minute',
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
            'bonus_wallet_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'admin_receivable_due' => 'decimal:2',
            'margin_accrued_total' => 'decimal:2',
            'due_grace_period_days' => 'integer',
            'default_prepaid_grace_days' => 'integer',
            'default_postpaid_grace_days' => 'integer',
            'reseller_can_override_charge_mode' => 'boolean',
            'allow_overdue_customers_active' => 'boolean',
            'suspend_reseller_customers_on_breach' => 'boolean',
            'risk_score' => 'decimal:2',
            'billing_policy_evaluated_at' => 'datetime',
            'low_balance_threshold' => 'decimal:2',
            'auto_suspend_on_low_balance' => 'boolean',
            'auto_restore_on_recharge' => 'boolean',
            'allowed_ips' => 'array',
            'api_access_enabled' => 'boolean',
            'api_rate_limit_per_minute' => 'integer',
            'hierarchy_depth' => 'integer',
            'max_onu' => 'integer',
            'max_olt' => 'integer',
            'bandwidth_quota_mbps' => 'integer',
            'max_packages' => 'integer',
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
        $base = is_array($custom) && $custom !== []
            ? array_values(array_intersect($custom, ResellerPortalPermission::all()))
            : ResellerPortalPermission::defaultsFor((string) ($this->franchise_type ?: ResellerType::RESELLER));

        $merged = array_merge($base, ResellerPortalPermission::alwaysGranted());

        if (config('reseller_enterprise.merge_default_permissions', true)) {
            $merged = array_merge($merged, ResellerPortalPermission::enterpriseDefaults());
        }

        if ($this->api_access_enabled) {
            $merged[] = ResellerPortalPermission::API_KEYS_MANAGE;
        }

        return array_values(array_unique($merged));
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

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(ResellerWalletTransaction::class);
    }

    public function commissionTiers(): HasMany
    {
        return $this->hasMany(ResellerCommissionTier::class);
    }

    public function customerTransfersFrom(): HasMany
    {
        return $this->hasMany(ResellerCustomerTransfer::class, 'from_reseller_id');
    }

    public function customerTransfersTo(): HasMany
    {
        return $this->hasMany(ResellerCustomerTransfer::class, 'to_reseller_id');
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ResellerApiKey::class);
    }

    public function portalLoginLogs(): HasMany
    {
        return $this->hasMany(ResellerPortalLoginLog::class);
    }

    public function internalTickets(): HasMany
    {
        return $this->hasMany(ResellerInternalTicket::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ResellerNote::class);
    }

    public function resellerInvoices(): HasMany
    {
        return $this->hasMany(ResellerInvoice::class);
    }

    public function customRoles(): HasMany
    {
        return $this->hasMany(ResellerCustomRole::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ResellerLedgerEntry::class);
    }

    public function monthlyStatements(): HasMany
    {
        return $this->hasMany(ResellerMonthlyStatement::class);
    }

    public function totalWalletBalance(): float
    {
        return (float) $this->wallet_balance + (float) $this->bonus_wallet_balance;
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
            'bonus_wallet' => (float) $this->bonus_wallet_balance,
            'total_wallet' => $this->totalWalletBalance(),
            'credit_limit' => (float) $this->credit_limit,
            'admin_receivable_due' => (float) $this->admin_receivable_due,
            'margin_accrued_total' => (float) $this->margin_accrued_total,
            'hierarchy_depth' => (int) $this->hierarchy_depth,
        ];
    }
}
