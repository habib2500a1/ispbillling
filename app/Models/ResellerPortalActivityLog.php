<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerPortalActivityLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'reseller_staff_id',
        'action',
        'subject_type',
        'subject_id',
        'meta',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(ResellerStaff::class, 'reseller_staff_id');
    }

    public function actionLabel(): string
    {
        return \App\Support\ResellerPortalActivityLabels::label($this->action);
    }

    public function actorLabel(): string
    {
        if ($this->staff) {
            return $this->staff->name.' (staff)';
        }

        return $this->reseller?->name ?? 'Owner';
    }

    public function subjectLabel(): ?string
    {
        if ($this->subject_type === null || $this->subject_id === null) {
            return null;
        }

        $base = class_basename($this->subject_type);

        return $base.' #'.$this->subject_id;
    }
}
