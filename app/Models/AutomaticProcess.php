<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomaticProcess extends Model
{
    use BelongsToTenant;

    public const INTERVALS = [
        'every_minute' => 'Every minute',
        'every_two_minutes' => 'Every 2 minutes',
        'every_three_minutes' => 'Every 3 minutes',
        'every_five_minutes' => 'Every 5 minutes',
        'every_ten_minutes' => 'Every 10 minutes',
        'every_fifteen_minutes' => 'Every 15 minutes',
        'every_thirty_minutes' => 'Every 30 minutes',
        'hourly' => 'Hourly',
        'daily' => 'Daily',
    ];

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'slug',
        'name',
        'description',
        'artisan_command',
        'command_options',
        'execute_at',
        'interval',
        'enabled',
        'when_config_key',
        'without_overlapping_minutes',
        'last_run_at',
        'last_status',
        'last_output',
        'next_run_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'command_options' => 'array',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomaticProcessRun::class);
    }

    public function isBuiltIn(): bool
    {
        return in_array($this->slug, self::builtInSlugs(), true);
    }

    /**
     * @return list<string>
     */
    public static function builtInSlugs(): array
    {
        return [
            'generate-bills-daily',
            'generate-bills-monthly',
            'generate-bills-hourly',
            'apply-late-fees',
            'invoice-due-reminders',
            'evaluate-service-expiry',
            'network-evaluate-access',
            'disable-unpaid-customers',
            'check-sms-health',
            'mikrotik-session-integrity',
            'mikrotik-fetch-details',
            'auto-link-customer-onus',
            'fup-usage-alerts',
            'apply-scheduled-package-changes',
            'prepaid-wallet-settle',
            'piprapay-sync-pending',
            'sync-customers-from-servers',
            'check-reseller-negative-balance',
            'postpaid-pop-fund-credit',
            'mikrotik-poll-status',
            'import-mikrotik-secrets',
            'collect-bandwidth',
            'poll-olt-intelligence',
            'ispdigital-onu-sync',
            'sync-bdcom-epon-onus',
            'collect-onu-signals',
            'ensure-customer-onus',
            'smart-link-customer-onus',
            'process-netflow-inbox',
            'sync-onu-status-from-meta',
            'support-check-sla',
            'sales-lead-follow-ups',
            'broadcast-dashboard-metrics',
            'platform-backup',
        ];
    }

    public function executionDayLabel(): string
    {
        if ($this->next_run_at === null) {
            return '—';
        }

        if ($this->next_run_at->isToday()) {
            return 'Today';
        }

        if ($this->next_run_at->isTomorrow()) {
            return 'Tomorrow';
        }

        return $this->next_run_at->format('M j');
    }

    public function executeAtLabel(): string
    {
        if (in_array($this->interval, ['every_minute', 'every_two_minutes', 'every_three_minutes', 'every_five_minutes', 'every_ten_minutes', 'every_fifteen_minutes', 'every_thirty_minutes', 'hourly'], true)) {
            return 'Default';
        }

        return $this->execute_at ?: '00:00';
    }
}
