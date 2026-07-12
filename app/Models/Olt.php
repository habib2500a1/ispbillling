<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Olt extends Model
{
    protected $fillable = [
        'name',
        'vendor',
        'olt_driver',
        'model',
        'location',
        'management_ip',
        'snmp_host',
        'snmp_port',
        'snmp_community',
        'snmp_version',
        'telnet_port',
        'ssh_port',
        'ssh_username',
        'ssh_password',
        'status',
        'olt_health',
        'last_health_polled_at',
        'last_snmp_poll_at',
        'notes',
        'meta',
    ];

    protected $hidden = [
        'snmp_community',
        'ssh_password',
    ];

    protected function casts(): array
    {
        return [
            'olt_health' => 'array',
            'meta' => 'array',
            'last_health_polled_at' => 'datetime',
            'last_snmp_poll_at' => 'datetime',
            'snmp_port' => 'integer',
            'telnet_port' => 'integer',
            'ssh_port' => 'integer',
        ];
    }

    public function ports(): HasMany
    {
        return $this->hasMany(OltPort::class);
    }

    public function healthLogs(): HasMany
    {
        return $this->hasMany(OltHealthLog::class);
    }

    public function snmpPeerHost(): string
    {
        $host = filled($this->snmp_host) ? trim((string) $this->snmp_host) : trim((string) ($this->management_ip ?? ''));

        return $host;
    }
}
