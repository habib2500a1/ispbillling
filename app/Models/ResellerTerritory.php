<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerTerritory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'area_id',
        'zone_id',
        'subzone_id',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
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

    public function label(): string
    {
        if ($this->subzone) {
            return $this->subzone->name.' (subzone)';
        }
        if ($this->zone) {
            return $this->zone->name.' (zone)';
        }
        if ($this->area) {
            return $this->area->name.' (area)';
        }

        return '—';
    }
}
