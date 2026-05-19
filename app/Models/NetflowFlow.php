<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetflowFlow extends Model
{
    protected $fillable = [
        'tenant_id',
        'netflow_exporter_id',
        'exporter_ip',
        'src_ip',
        'dst_ip',
        'src_port',
        'dst_port',
        'protocol',
        'bytes',
        'packets',
        'flow_start',
        'flow_end',
        'sampled_at',
    ];

    protected function casts(): array
    {
        return [
            'flow_start' => 'datetime',
            'flow_end' => 'datetime',
            'sampled_at' => 'datetime',
        ];
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(NetflowExporter::class, 'netflow_exporter_id');
    }
}
