<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportRootIncident extends Model
{
    use BelongsToTenant;

    public const STATUSES = [
        'active' => 'Active',
        'resolved' => 'Resolved',
    ];

    protected $fillable = [
        'tenant_id',
        'incident_number',
        'title',
        'description',
        'status',
        'olt_device_id',
        'pop_box_id',
        'area_id',
        'primary_ticket_id',
        'ticket_count',
        'detected_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'ticket_count' => 'integer',
        ];
    }

    public static function generateNumber(int $tenantId): string
    {
        $prefix = 'INC-'.now()->format('Y').'-';
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('incident_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('incident_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'olt_device_id');
    }

    public function popBox(): BelongsTo
    {
        return $this->belongsTo(PopBox::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function primaryTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'primary_ticket_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'root_incident_id');
    }
}
