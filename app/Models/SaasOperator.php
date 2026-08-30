<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasOperator extends Model
{
    protected $fillable = [
        'user_id',
        'saas_plan_id',
        'company',
        'contact_name',
        'email',
        'phone',
        'domain',
        'plan',
        'billing_cycle',
        'base_amount',
        'per_user_rate',
        'user_base_count',
        'amount',
        'status',
        'can_resell',
        'notes',
        'sold_at',
        'next_due_at',
        'last_paid_at',
        'locked_at',
        'lock_reason',
        'max_customers',
        'max_olts',
        'max_onus',
        'max_routers',
        'max_staff',
        'modules',
    ];

    protected $casts = [
        'can_resell' => 'boolean',
        'sold_at' => 'datetime',
        'next_due_at' => 'datetime',
        'last_paid_at' => 'datetime',
        'locked_at' => 'datetime',
        'modules' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function planCatalog(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class);
    }

    public function staffCashEntries(): HasMany
    {
        return $this->hasMany(StaffCashEntry::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAccessBlocked(): bool
    {
        return in_array($this->status, ['locked', 'suspended'], true);
    }

    public function isLifetime(): bool
    {
        return $this->billing_cycle === 'lifetime';
    }

    public function allowsModule(string $module): bool
    {
        $modules = $this->modules;
        if (! is_array($modules) || $modules === []) {
            return true;
        }

        return in_array($module, $modules, true) || in_array('*', $modules, true);
    }
}
