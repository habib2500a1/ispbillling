<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ResellerType;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait, BelongsToTenant;

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
                $q->where('code', $login)->orWhere('email', $login);
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
        'phone',
        'email',
        'portal_password',
        'portal_last_login_at',
        'contact_person',
        'commission_type',
        'commission_value',
        'revenue_share_percent',
        'white_label_enabled',
        'brand_name',
        'brand_logo_path',
        'brand_primary_color',
        'portal_subdomain',
        'wallet_balance',
        'is_active',
        'notes',
    ];

    protected $hidden = [
        'portal_password',
        'remember_token',
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
            'commission_value' => 'decimal:2',
            'revenue_share_percent' => 'decimal:2',
            'wallet_balance' => 'decimal:2',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'parent_id');
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
