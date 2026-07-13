<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomaticProcessRun extends Model
{
    protected $fillable = [
        'automatic_process_id',
        'triggered_by',
        'status',
        'exit_code',
        'output',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(AutomaticProcess::class, 'automatic_process_id');
    }
}
