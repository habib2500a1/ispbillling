<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiberPlantNode extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'latitude',
        'longitude',
        'address',
        'pop_box_id',
        'device_id',
        'customer_id',
        'splitter_ratio',
        'splitter_direction',
        'bearing_deg',
        'meta',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'meta' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function popBox(): BelongsTo
    {
        return $this->belongsTo(PopBox::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(FiberPlantEdge::class, 'from_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(FiberPlantEdge::class, 'to_node_id');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function typeLabel(): string
    {
        return (string) (config('fiber_plant.node_types.'.$this->type.'.label') ?? ucfirst($this->type));
    }
}
