<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\CreatesFromTrustedSource;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use App\Services\Subscribers\SubscriberPolicyService;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model implements AuthenticatableContract, AuthorizableContract
{
    use AuthenticatableTrait, Authorizable, BelongsToTenant, CreatesFromTrustedSource, HasApiTokens;

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if (blank($customer->customer_code) && \App\Support\SubscriberIdSettings::autoGenerateEnabled()) {
                $customer->customer_code = \App\Support\CustomerCodeGenerator::generate(
                    (int) $customer->tenant_id,
                    $customer->mikrotik_secret_name,
                );
            }
        });

        static::created(function (Customer $customer): void {
            if (! config('optical.auto_provision_customer_onu', true)) {
                return;
            }

            app(\App\Services\Optical\CustomerOnuAutoProvisionService::class)->ensureForCustomer($customer);

            if (config('optical.auto_sync_on_customer_save', true)) {
                \App\Support\OpticalCustomerSync::dispatch($customer, true);
            }
        });

        static::deleting(function (Customer $customer): void {
            app(\App\Services\Subscribers\CustomerDeletionService::class)->prepareDelete($customer);
        });

        static::saved(function (Customer $customer): void {
            if ($customer->wasChanged([
                'mikrotik_secret_name',
                'radius_username',
                'customer_code',
                'phone',
                'mikrotik_server_id',
            ])) {
                \App\Support\CustomerPppLoginResolver::clearIndexCache();
            }

            $meta = is_array($customer->meta) ? $customer->meta : [];
            $opticalMetaChanged = $customer->wasChanged('meta')
                && (
                    filled($meta['onu_mac'] ?? null)
                    || filled($meta['mac_binding'] ?? null)
                    || filled($meta['epon_port'] ?? null)
                );

            if (
                config('optical.auto_sync_on_customer_save', true)
                && ! $customer->wasRecentlyCreated
                && ($customer->wasChanged(['mikrotik_secret_name', 'radius_username', 'customer_code']) || $opticalMetaChanged)
            ) {
                \App\Support\OpticalCustomerSync::dispatch($customer, true);
            }
        });
    }

    public static function generateCustomerCode(int $tenantId): string
    {
        return \App\Support\CustomerCodeGenerator::generate($tenantId);
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
            ->whereNotNull('portal_password')
            ->where('status', CustomerStatus::ACTIVE)
            ->where(function ($q) use ($login, $digits): void {
                $q->where('customer_code', $login);
                if ($digits !== '') {
                    $q->orWhere('phone', $digits)->orWhere('phone', $login);
                } else {
                    $q->orWhere('phone', $login);
                }
                $q->orWhere('email', $login);
                if ($digits !== '') {
                    $q->orWhereHas('contacts', function ($cq) use ($digits, $login): void {
                        $cq->where('phone', $digits)->orWhere('phone', $login);
                    });
                }
            })
            ->first();
    }

    /**
     * Mass-assignable profile fields (admin forms). Financial / network state uses createTrusted() or forceFill().
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_code',
        'name',
        'phone',
        'email',
        'nid_number',
        'nid_front_path',
        'nid_back_path',
        'photo_path',
        'area_id',
        'zone_id',
        'subzone_id',
        'reseller_id',
        'package_id',
        'pending_package_id',
        'pending_package_effective_date',
        'status',
        'subscriber_type',
        'auto_suspend_override',
        'billing_day',
        'account_balance',
        'credit_limit',
        'pending_reconnection_fee',
        'portal_password',
        'portal_last_login_at',
        'portal_last_logout_at',
        'network_access_state',
        'notes',
        'meta',
        'joined_at',
        'service_expires_at',
        'radius_username',
        'mikrotik_secret_name',
        'mikrotik_server_id',
        'mikrotik_ppp_password',
        'kyc_status',
        'kyc_verified_at',
        'kyc_notes',
        'segment',
        'address',
        'billing_mode',
        'grace_period_days',
        'late_fee_fixed',
        'late_fee_percent',
        'late_fee_period',
        'reconnection_fee_amount',
        'security_deposit_required',
        'security_deposit_collected',
    ];

    protected $hidden = [
        'portal_password',
        'mikrotik_ppp_password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'pending_package_effective_date' => 'date',
            'service_expires_at' => 'date',
            'account_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'pending_reconnection_fee' => 'boolean',
            'mikrotik_ppp_password' => \App\Casts\SafeEncryptedString::class,
            'mikrotik_synced_at' => 'datetime',
            'is_ppp_online' => 'boolean',
            'ppp_last_seen_at' => 'datetime',
            'portal_last_login_at' => 'datetime',
            'portal_last_logout_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'late_fee_fixed' => 'decimal:2',
            'late_fee_percent' => 'decimal:2',
            'reconnection_fee_amount' => 'decimal:2',
            'meta' => 'array',
            'security_deposit_required' => 'decimal:2',
            'security_deposit_collected' => 'decimal:2',
        ];
    }

    public function subscriberTypeLabel(): string
    {
        return SubscriberType::label(SubscriberType::normalize((string) ($this->subscriber_type ?? SubscriberType::STANDARD)));
    }

    public function subscriberTypeColor(): string
    {
        return SubscriberType::color(SubscriberType::normalize((string) ($this->subscriber_type ?? SubscriberType::STANDARD)));
    }

    public function shouldGenerateInvoice(): bool
    {
        return app(SubscriberPolicyService::class)->shouldGenerateInvoice($this);
    }

    public function isExemptFromAutoNetworkSuspend(): bool
    {
        return app(SubscriberPolicyService::class)->isExemptFromAutoNetworkSuspend($this);
    }

    public function scopeBillable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where('status', CustomerStatus::ACTIVE)
            ->where('subscriber_type', '!=', SubscriberType::FREE)
            ->whereNotNull('package_id');
    }

    public function scopeSubscriberType(\Illuminate\Database\Eloquent\Builder $query, string $type): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('subscriber_type', SubscriberType::normalize($type));
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->portal_password ?? '');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function subzone(): BelongsTo
    {
        return $this->belongsTo(Subzone::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function pendingPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'pending_package_id');
    }

    public function mikrotikServer(): BelongsTo
    {
        return $this->belongsTo(MikrotikServer::class);
    }

    public function isPppOnline(): bool
    {
        $bandwidth = app(\App\Services\Bandwidth\BandwidthCollectionService::class);
        $tenantId = (int) $this->tenant_id;

        if (! $bandwidth->tenantOnlineFlagsTrustworthy($tenantId)) {
            return false;
        }

        return (bool) $this->is_ppp_online;
    }

    /**
     * PPP login name on RouterOS (secret name).
     */
    public function pppLoginName(): string
    {
        if (filled($this->mikrotik_secret_name)) {
            return (string) $this->mikrotik_secret_name;
        }

        if (filled($this->radius_username)) {
            return (string) $this->radius_username;
        }

        return (string) $this->customer_code;
    }

    public function formattedAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->subzone?->name,
            $this->zone?->name,
            $this->area?->name,
        ], fn (?string $part): bool => filled($part));

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Sum of unpaid balances on open invoices (monthly bills, fees, etc.).
     */
    public function openInvoiceBalance(): float
    {
        $sum = $this->invoices()
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->get(['total', 'amount_paid'])
            ->sum(fn (Invoice $invoice): float => max(0, (float) $invoice->total - (float) $invoice->amount_paid));

        return round((float) $sum, 2);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class)->latest();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function lineActivations(): HasMany
    {
        return $this->hasMany(CustomerLineActivation::class)->latest();
    }

    public function onuDevice(): HasOne
    {
        return $this->hasOne(Device::class)
            ->where('type', 'onu')
            ->latestOfMany('id');
    }

    public function primaryOnu(): ?Device
    {
        if ($this->relationLoaded('onuDevice')) {
            return $this->onuDevice;
        }

        if ($this->relationLoaded('devices')) {
            return $this->devices->firstWhere('type', 'onu');
        }

        return $this->onuDevice()->first();
    }

    public function pppSessions(): HasMany
    {
        return $this->hasMany(PppSessionLog::class)->latest('started_at');
    }

    public function activePppSession(): HasOne
    {
        return $this->hasOne(PppSessionLog::class)
            ->where('status', 'active')
            ->latestOfMany('started_at');
    }

    public function latestPppSession(): HasOne
    {
        return $this->hasOne(PppSessionLog::class)->latestOfMany('started_at');
    }

    public function lastEndedPppSession(): HasOne
    {
        return $this->hasOne(PppSessionLog::class)
            ->whereNotNull('ended_at')
            ->latestOfMany('ended_at');
    }

    /**
     * Subscribers provisioned on MikroTik PPP (imported or linked).
     */
    public function scopeWithMikrotikPpp(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q): void {
            $q->whereNotNull('mikrotik_secret_name')
                ->orWhereNotNull('mikrotik_server_id')
                ->orWhere('import_source', 'mikrotik');
        });
    }

    public function bandwidthUsageDaily(): HasMany
    {
        return $this->hasMany(BandwidthUsageDaily::class)->latest('usage_date');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class)->latest();
    }

    public function customerNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->orderByDesc('is_pinned')->latest();
    }

    public function primaryContact(): ?CustomerContact
    {
        return $this->contacts->firstWhere('is_primary', true)
            ?? $this->contacts->first();
    }

    public function syncPrimaryPhoneFromContacts(): void
    {
        $primary = $this->contacts()->where('is_primary', true)->first()
            ?? $this->contacts()->orderBy('id')->first();

        if ($primary === null) {
            return;
        }

        $normalized = CustomerContact::normalizePhone($primary->phone);
        if ($normalized !== '' && $this->phone !== $normalized) {
            $this->forceFill(['phone' => $normalized])->saveQuietly();
        }
    }

    public function statusLabel(): string
    {
        return CustomerStatus::label(CustomerStatus::normalize((string) $this->status));
    }

    public function statusColor(): string
    {
        return CustomerStatus::color(CustomerStatus::normalize((string) $this->status));
    }

    public function portalAccessEnabled(): bool
    {
        return filled($this->portal_password);
    }

    public function recordPortalLogin(): void
    {
        $this->forceFill(['portal_last_login_at' => now()])->saveQuietly();
    }

    public function recordPortalLogout(): void
    {
        $this->forceFill(['portal_last_logout_at' => now()])->saveQuietly();
    }

    /**
     * Service date is strictly before today (last valid day is the expiry date).
     */
    public function isServiceExpired(): bool
    {
        if ($this->service_expires_at === null) {
            return false;
        }

        return now()->toDateString() > $this->service_expires_at->toDateString();
    }

    /**
     * First calendar day the line is off by validity (day after service_expires_at).
     */
    public function serviceOffDate(): ?\Carbon\Carbon
    {
        if ($this->service_expires_at === null) {
            return null;
        }

        return $this->service_expires_at->copy()->addDay()->startOfDay();
    }

    /**
     * Days until last valid day (negative if already expired).
     */
    public function daysUntilServiceExpiry(): ?int
    {
        if ($this->service_expires_at === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->service_expires_at->copy()->startOfDay(), false);
    }

    public function serviceExpirySummary(): string
    {
        if ($this->service_expires_at === null) {
            return 'No expiry set';
        }

        $validUntil = $this->service_expires_at->format('d M Y');
        $offFrom = $this->serviceOffDate()?->format('d M Y');
        $days = $this->daysUntilServiceExpiry();

        if ($this->isServiceExpired()) {
            return "Expired · was valid until {$validUntil} · off since {$offFrom}";
        }

        if ($days === 0) {
            return "Valid until today ({$validUntil}) · off from tomorrow";
        }

        return "Valid until {$validUntil} ({$days} days) · off from {$offFrom}";
    }
}
