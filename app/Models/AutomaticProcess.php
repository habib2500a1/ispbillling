<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomaticProcess extends Model
{
    public const INTERVALS = [
        'every_minute' => 'Every minute',
        'every_five_minutes' => 'Every 5 minutes',
        'every_fifteen_minutes' => 'Every 15 minutes',
        'every_thirty_minutes' => 'Every 30 minutes',
        'hourly' => 'Hourly',
        'daily' => 'Daily',
    ];

    protected $fillable = [
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
            'generate-monthly-bills',
            'monthly-bill-sms',
            'payment-reminder-alerts',
            'disable-unpaid-users',
            'poll-ppp-online',
            'poll-router-logs',
            'olt-health-poll',
            'saas-lock-overdue',
            'prune-router-logs',
        ];
    }

    public function intervalLabel(): string
    {
        return self::INTERVALS[$this->interval] ?? $this->interval;
    }

    public function executeAtLabel(): string
    {
        if (in_array($this->interval, ['every_minute', 'every_five_minutes', 'every_fifteen_minutes', 'every_thirty_minutes', 'hourly'], true)) {
            return '—';
        }

        return $this->execute_at ?: '00:00';
    }
}
