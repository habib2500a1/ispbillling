<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiberPlantEdge extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'from_node_id',
        'to_node_id',
        'cable_type',
        'fiber_count',
        'cable_color',
        'tube_color',
        'length_m',
        'direction_label',
        'bearing_deg',
        'core_map',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'length_m' => 'decimal:2',
            'core_map' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(FiberPlantNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(FiberPlantNode::class, 'to_node_id');
    }

    public function cableColorHex(): string
    {
        $key = $this->cable_color ?? 'blue';

        return (string) (config('fiber_plant.cable_colors.'.$key.'.hex') ?? '#2563eb');
    }

    public function cableTypeLabel(): string
    {
        return (string) (config('fiber_plant.cable_types.'.$this->cable_type) ?? ucfirst($this->cable_type));
    }
}
