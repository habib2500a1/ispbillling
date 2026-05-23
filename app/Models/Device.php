<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'type',
        'connection_type',
        'vendor',
        'olt_driver',
        'gpon_profile',
        'display_name',
        'location',
        'serial_number',
        'mac_address',
        'model',
        'management_ip',
        'snmp_host',
        'snmp_port',
        'snmp_username',
        'onu_external_id',
        'card_no',
        'pon_no',
        'onu_index',
        'vlan_id',
        'framed_ip_address',
        'rx_power_dbm',
        'tx_power_dbm',
        'provisioned_at',
        'last_polled_at',
        'onu_oper_status',
        'offline_reason',
        'olt_id',
        'olt_port_id',
        'customer_id',
        'status',
        'lease_status',
        'lease_monthly_fee',
        'lease_started_at',
        'lease_ended_at',
        'mac_binding_strict',
        'serial_binding_strict',
        'authorization_password',
        'snmp_community',
        'snmp_version',
        'telnet_port',
        'ssh_port',
        'ssh_username',
        'ssh_password',
        'olt_health',
        'last_health_polled_at',
        'last_snmp_poll_at',
        'notes',
        'meta',
    ];

    protected $hidden = [
        'snmp_community',
        'authorization_password',
        'ssh_password',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function catalogProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'olt_id');
    }

    public function oltPort(): BelongsTo
    {
        return $this->belongsTo(OltPort::class, 'olt_port_id');
    }

    public function ports(): HasMany
    {
        return $this->hasMany(OltPort::class, 'device_id');
    }

    public function onus(): HasMany
    {
        return $this->hasMany(self::class, 'olt_id');
    }

    public function onuSignalLogs(): HasMany
    {
        return $this->hasMany(OnuSignalLog::class);
    }

    public function oltHealthLogs(): HasMany
    {
        return $this->hasMany(OltHealthLog::class);
    }

    public function onuHealthScore(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OnuHealthScore::class);
    }

    public function scopeOlts(Builder $query): Builder
    {
        return $query->where('type', 'olt');
    }

    public function scopeNonOlts(Builder $query): Builder
    {
        return $query->where('type', '!=', 'olt');
    }

    /** Human label for admin / portal (OLT name or serial). */
    public function adminLabel(): string
    {
        if ($this->type === 'olt' && filled($this->display_name)) {
            return (string) $this->display_name;
        }

        if (filled($this->display_name)) {
            return (string) $this->display_name;
        }

        return (string) $this->serial_number;
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'olt_health' => 'array',
            'provisioned_at' => 'datetime',
            'last_polled_at' => 'datetime',
            'last_health_polled_at' => 'datetime',
            'last_snmp_poll_at' => 'datetime',
            'lease_started_at' => 'datetime',
            'lease_ended_at' => 'datetime',
            'rx_power_dbm' => 'decimal:3',
            'tx_power_dbm' => 'decimal:3',
            'lease_monthly_fee' => 'decimal:2',
            'mac_binding_strict' => 'boolean',
            'serial_binding_strict' => 'boolean',
            'snmp_port' => 'integer',
            'authorization_password' => 'encrypted',
            'snmp_community' => 'encrypted',
            'ssh_password' => 'encrypted',
        ];
    }
}
